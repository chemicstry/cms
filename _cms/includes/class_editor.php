<?php
	// #######################
	//   Layout Editor Class
	// #######################

	if (!FAKE)
		exit;
	
	class Editor {
        public static $m_data;
        public static $m_pageid;
        public static $m_moduleid;
        public static $m_extra_head;
        public static $m_scripts = array();
		
		public static function DeleteModule($a_id, $a_force = false) {
			$id = Database::Escape($a_id);
			$result = Database::Query("SELECT * FROM `" . DB_TBL_MODULE_TEMPLATE . "` WHERE `id` = '" . $id . "'");
			
			if (!$result->HasData())
				die("Editor::DeleteModule: Module #" . $a_id . " not found");
			
			$module_template = $result->GetRow();
			
			if ($module_template['type'] == "static" && !$a_force)
				die("Editor::DeleteModule: Can't delete static module #" . $a_id);
			
			// Delete
			Database::Query("DELETE FROM `" . DB_TBL_MODULE_TEMPLATE . "` WHERE `id` = '" . $id . "'");
			Database::Query("DELETE FROM `" . DB_TBL_MODULE . "` WHERE `id` = '" . $id . "'");
			Database::Query("DELETE FROM `" . DB_TBL_DATA . "` WHERE `moduleid` = '" . $id . "'");
			
			print 'ok';
		}
		
		public static function UpdatePage($a_data) {
			$name = Database::Escape(serialize($a_data['name']));
			
            // Update page if we have id, else add new one
            if ($a_data['id']) {
                $id = Database::Escape($a_data['id']);
                Database::Query("UPDATE `" . DB_TBL_PAGES . "` SET `name` = '" . $name . "' WHERE `id` = '" . $id . "'");
            } else {
                if (!is_file(COMPILER_TEMPLATES_DIR . '/' . $a_data['template'] . ".tmpl"))
                    die("Editor::CreatePage: Template not found: " . $a_data['template']);
                
                $template = Database::Escape($a_data['template']);
                
                Database::Query("INSERT INTO `" . DB_TBL_PAGES . "` (`name`, `template`) VALUES ('" . $name . "', '" . $template . "')");
            }
			
            if (!$id)
                $id = Database::GetLastIncrId();
			
			$page_data = array();
			$page_data['id'] = $id;
			$page_data['name'] = $a_data['name'];
			$page_data['template'] = $a_template;
			$page_data['default'] = 0;
			
			print json_encode($page_data);
		}
		
        public static function SavePage($a_data) {
            self::$m_pageid = Database::Escape($a_data['pageid']);
            
            Database::Query("DELETE FROM `" . DB_TBL_MODULE . "` WHERE `pageid` = '" . self::$m_pageid . "' AND `container` != 'global'");
            
            if (isset($a_data['containers'])) {
                foreach ($a_data['containers'] as $container) {
                    if (!isset($container['modules']))
                        continue;
                    
                    foreach ($container['modules'] as $module) {
                        Database::Query("INSERT INTO `" . DB_TBL_MODULE . "` (`id`, `pageid`, `container`, `slot`) VALUES ('" . $module['id'] . "', '" . self::$m_pageid . "', '" . $container['name'] . "', '" . $module['slot'] . "')");
                    }
                }
            }
            
            $compiler = new Compiler();
            $compiler->CompilePage(self::$m_pageid);
        }
		
		public static function DeletePage($a_id) {
			$id = Database::Escape($a_id);
			
			Database::Query("DELETE FROM `" . DB_TBL_MODULE . "` WHERE `pageid` = '" . $id . "'");
			Database::Query("DELETE FROM `" . DB_TBL_PAGES . "` WHERE `id` = '" . $id . "'");
			
			$result = Database::Query("SELECT `id` FROM `" . DB_TBL_PAGES . "` WHERE `default` = '1'");
			if (!$result->HasData())
				Database::Query("UPDATE `" . DB_TBL_PAGES . "` SET `default` = '1' LIMIT 1");
		}
		
		public static function SetDefaultPage($a_id) {
			$id = Database::Escape($a_id);
			
			Database::Query("UPDATE `" . DB_TBL_PAGES . "` SET `default` = '0'");
			Database::Query("UPDATE `" . DB_TBL_PAGES . "` SET `default` = '1' WHERE `id` = '" . $id . "'");
		}
        
        public static function GeneratePageData() {
            $page_data = array();
            $page_data['ID'] = self::$m_pageid;
            $page_data['Pages'] = array();
            
            $result = Database::Query("SELECT `id`, `name`, `template`, `default` FROM `" . DB_TBL_PAGES . "`");
            
            do {
                $row = $result->GetRow();
                $page_data['Pages'][$row['id']]['ID'] = $row['id'];
                $page_data['Pages'][$row['id']]['Name'] = unserialize($row['name']);
                $page_data['Pages'][$row['id']]['Template'] = $row['template'];
                $page_data['Pages'][$row['id']]['Default'] = $row['default'];
            } while ($result->NextRow());
            
            self::AddData(DATA_PAGE, $page_data);
			
			// Generate template data
			// Page templates
			$page_templates = find_all_files(COMPILER_TEMPLATES_DIR);
			foreach ($page_templates as $page_template) {
				$templates_data['page'][] = substr($page_template, 0, -5);
			}
			// Module templates
			$module_types = find_all_dirs(COMPILER_TEMPLATES_DIR . '/modules');
			foreach ($module_types as $module_type) {
				$module_templates = find_all_files(COMPILER_TEMPLATES_DIR . '/modules/' . $module_type);
				foreach ($module_templates as $module_template) {
					$templates_data['modules'][$module_type][] = substr($module_template, 0, -5);
				}
			}
			self::AddData(DATA_TEMPLATES, $templates_data);
        }
		
		public static function GenerateModulesData() {
			$result = Database::Query("SELECT * FROM `" . DB_TBL_MODULE_TEMPLATE . "`");
			
			if (!$result->HasData())
				return;
			
			do {
				$module_template = $result->GetRow();
				
                // Add data
                $data = array();
                $data['id'] = $module_template['id'];
                $data['type'] = $module_template['type'];
                $data['template'] = $module_template['template'];
				$data['name'] = $module_template['name'];
                
                //$iterators = Module::GenerateIteratorStructures($module_template);
                if (count($iterators))
                    $data['iterators'] = $iterators;
                Editor::AddData(DATA_MODULE, $data);
			} while ($result->NextRow());
		}
		
		public static function GenerateModuleData($a_id) {
			$id = Database::Escape($a_id);
			
			$result = Database::Query("SELECT * FROM `" . DB_TBL_MODULE_TEMPLATE . "` WHERE `id` = '" . $id . "'");
			
			if (!$result->HasData())
				die("Editor::GenerateModuleData: module #" . $a_id . " not found");
			
			$module_template = $result->GetRow();
			
			// Add data
            $data = array();
            $data['id'] = $module_template['id'];
            $data['type'] = $module_template['type'];
            $data['template'] = $module_template['template'];
			$data['name'] = $module_template['name'];
               
            //$iterators = Module::GenerateIteratorStructures($module_template);
            if (count($iterators))
                $data['iterators'] = $iterators;
			
			return $data;
		}
        
        public static function SaveModule($a_data) {
            //print_r($_POST);
            self::$m_pageid = Database::Escape($_POST['page_id']);
            self::$m_moduleid = Database::Escape($_POST['module_id']);
            
            // Delete old data
            Database::Query("DELETE FROM `" . DB_TBL_DATA . "` WHERE `moduleid` = '" . self::$m_moduleid . "'");
            
            self::SaveModuleFragment($a_data, self::$m_moduleid);
            
            $module = new Module(self::$m_moduleid);
            $doc = $module->Build(); 
            
            // Plugin Hook
            $data_object = new stdClass();
            $data_object->doc = $doc;
            ObjMgr::GetPluginMgr()->ExecuteHook("On_PrepareTemplate", $data_object);
            
            print json_encode(array("moduleid" => self::$m_moduleid, "content" => $doc->getHtml(), "module_data" => self::$m_data['module_data']));
            
            // Page needs recompiling
            $result = Database::Query("SELECT `pageid` FROM `" . DB_TBL_MODULE . "` WHERE `id` = '" . self::$m_moduleid . "' GROUP BY `pageid`");
            if ($result->HasData()) {
                do {
                    Database::Query("UPDATE `" . DB_TBL_PAGES . "` SET `compiled` = '' WHERE `id` = '" . $result->GetValue('pageid') . "'");
                } while ($result->NextRow());
            }
        }
        
        public static function SaveModuleFragment($a_fragment, $a_owner) {
            foreach ($a_fragment as $object)
            {
                // Plugin Hook
                $data_object = new stdClass();
                $data_object->object = $object;
                $data_object->owner = $a_owner;
                $data_object->moduleid = self::$m_moduleid;
                ObjMgr::GetPluginMgr()->ExecuteHook("On_Editor_SaveModuleFragmentObject", $data_object);
            }
        }
        
        public static function GetPage($a_id) {
            self::$m_pageid = $a_id;
            
            $result = Database::Query("SELECT * FROM `" . DB_TBL_PAGES . "` WHERE `id` = '" . Database::Escape($a_id) . "'");
            
            if (!$result->HasData())
                die("Page with id #" . $a_id . " not found!");
            
            Content::$m_pagename = unserialize($result->GetValue('name'));
            
            // jQuery
            Editor::LoadCSS("_cms/css/jQueryUI/" . JQUERY_UI_THEME . "/jquery-ui-" . JQUERY_UI_VERSION . ".custom.css");
            Editor::LoadJS("_cms/js/jquery-" . JQUERY_VERSION . ".min.js", 5);
            Editor::LoadJS("_cms/js/jquery-ui-" . JQUERY_UI_VERSION . ".custom.min.js", 4);
            
            // JS Render
            Editor::LoadJS("_cms/js/jquery.jsrender.min.js", 4);
            
            // CLEditor
            Editor::LoadCSS("_cms/js/cleditor/jquery.cleditor.css");
            Editor::LoadJS("_cms/js/cleditor/jquery.cleditor.js", 4);
            
            // Tipsy
            Editor::LoadCSS("_cms/css/tipsy.css");
            Editor::LoadJS("_cms/js/jquery.tipsy.js", 4);
            
            // jQuery Cookies
            Editor::LoadJS("_cms/js/jquery.cookie.js", 4);
            
            // KendoUI
            Editor::LoadCSS("_cms/css/kendo/kendo.common.min.css");
            Editor::LoadCSS("_cms/css/kendo/kendo." . KENDOUI_THEME . ".min.css");
            Editor::LoadJS("_cms/js/kendo.web.min.js", 4);
            
            // Editor
            //Editor::LoadCSS("_cms/css/editor.css");
            //Editor::LoadJS("_cms/js/editor.js", 3);
            Editor::LoadJS("_cms/js/cms.core.js", 3);
            Editor::LoadJS("_cms/js/cms.comm.js", 2);
            Editor::LoadJS("_cms/js/cms.locales.js", 2);
            Editor::LoadJS("_cms/js/cms.pluginsystem.js", 2);
            Editor::LoadJS("_cms/js/cms.toolbar.js", 2);
            Editor::LoadCSS("_cms/css/cms.toolbar.css");
            Editor::LoadJS("_cms/js/cms.objmgr.js", 2);
            Editor::LoadCSS("_cms/css/cms.module.css");
            
            $compiler = new Compiler();
            $doc = $compiler->CompilePage(self::$m_pageid, COMPILER_MODE_EDITOR);
            
            // Plugin Hook
            $data_object = new stdClass();
            $data_object->doc = $doc;
            ObjMgr::GetPluginMgr()->ExecuteHook("On_PrepareTemplate", $data_object);
            
            //Content::ProcessStrings($doc);
            
            // Add neccessary data
            $locale_list = array();
            foreach (Locales::$m_locales as $locale)
            {
                $loc_data = array();
                $loc_data['Name'] = $locale;
                $loc_data['ICO'] = Locales::GetConstString('ICO', $locale);
                $locale_list[] = $loc_data;
            }
			
            self::AddData(DATA_LOCALES, array(  'Default' => LOCALES_DEFAULT,
                                                'Current' => Locales::$m_locale, 
                                                'List' => $locale_list));
            self::AddData(DATA_STRINGS, Locales::$m_const_strings[Locales::$m_locale]);
            self::AddData(DATA_DEFINES, array(  'OPCodes' => get_class_consts("AJAXCommOPCodes"),
                                                'DebugMaskList' => get_class_consts("DebugMask"),
                                                'DebugMask' => LOG_DEBUGMASK,
                                                'AJAX_URL' => AJAX_URL));
            self::GeneratePageData();
			self::GenerateModulesData();
            
            self::InsertHeadContent($doc);
            //self::GenerateToolBar($doc);
            
            // Title
            Content::AddTitle($doc, Locales::GetConstString("PAGE_TITLE", NULL, Content::$m_pagename[Locales::$m_locale]));
            
            return $doc->getHtml();
        }
		
		public static function GetModule($a_id) {
			$id = Database::Escape($a_id);
			$module = new Module($id);
			
			$doc = $module->Build();
			
			// Plugin Hook
            $data_object = new stdClass();
            $data_object->doc = $doc;
            ObjMgr::GetPluginMgr()->ExecuteHook("On_PrepareTemplate", $data_object);
            
            $data = array();
            $data['html'] = $doc->getHtml();
            $data['module_data'] = self::$m_data['module_data'];
			
			return json_encode($data);
		}
        
        public static function AddData($a_type, $a_data)
        {
            switch ($a_type)
            {
                case DATA_CONTAINER:
                    self::$m_data['Containers'][] = $a_data;
                    break;
                case DATA_MODULE:
                    self::$m_data['Modules'][$a_data['id']] = $a_data;
                    break;
                case DATA_MODULE_DATA:
                    self::$m_data['ModuleData'][$a_data['ownerid']][] = $a_data;
                    break;
                case DATA_PAGE:
                    self::$m_data['Page'] = $a_data;
                    break;
                case DATA_LOCALES:
                    self::$m_data['Locales'] = $a_data;
                    break;
                case DATA_STRINGS:
                    self::$m_data['Strings'] = $a_data;
                    break;
				case DATA_TEMPLATES:
					self::$m_data['Templates'] = $a_data;
					break;
                case DATA_JAVASCRIPT:
                    self::$m_extra_head .= '<script type="text/javascript" src="' . $a_data . '"></script>';
                    break;
                case DATA_DEFINES:
                    self::$m_data['Defines'] = $a_data;
                    break;
                default:
                    die('Unknown data type passed to Editor::AddData');
            }
        }
        
        public static function GenerateToolBar($a_doc) {
            $toolbar_html = '<div id="editor-toolbar-container">
                                <div id="editor-toolbar" class="ui-widget ui-widget-content ui-corner-bottom">
                                    <div id="editor-toolbar-content">
                                        <div id="editor-toolbar-actions" class="ui-widget ui-widget-content ui-corner-all"></div><br/>
                                        <div id="editor-toolbar-pages" class="ui-widget ui-widget-content ui-corner-all"></div><br/>
                                        <div id="editor-toolbar-modules" class="ui-widget ui-widget-content ui-corner-all">
                                            <div id="editor-toolbar-modules-content">
                                            </div>
                                        </div>
                                    </div>
                                    <div id="editor-toolbar-tempeklis" class="ui-widget ui-widget-content ui-corner-bottom"><span class="ui-icon ui-icon-wrench"></span></div>
                                </div>
                            </div>';
            
            $bodys = $a_doc->getElementsByTag('CMS_BODY');
            $body = $bodys[0];
            
            // Add
            $body->addChild(new Template_TextNode($toolbar_html));
        }
        
        public static function LoadJS($a_file, $a_priority = 1) {
            if (!file_exists($a_file))
                die('Editor::LoadJS(): File "' . $a_file . '" not found');
            
            if ($a_priority < 1 || $a_priority > 9)
                die("Editor::LoadJS(): Wrong priority value '" . $a_priority . "'");
            
            self::$m_scripts[$a_priority] .= '<script type="text/javascript" src="' . GetRelativePath($a_file) . '"></script>';
        }
        
        public static function LoadCSS($a_file) {
            if (!file_exists($a_file))
                die('Editor::LoadCSS(): File "' . $a_file . '" not found');
            
            self::$m_extra_head .= '<link rel="stylesheet" type="text/css" href="' . GetRelativePath($a_file) . '"/>';
        }
        
        public static function InsertHeadContent($a_doc) {
            // Add scripts from array
            for ($itr = 9; $itr > 0; $itr--)
                self::$m_extra_head .= self::$m_scripts[$itr];
            
            // Get <HEAD> tag
            $heads = $a_doc->getElementsByTag('CMS_HEAD');
            $head = $heads[0];
            
            $head_html = '<script id="cms-data" type="application/json">' . str_replace("\\", "\\\\", json_encode(self::$m_data)) . '</script>' . self::$m_extra_head;
            
            // Add
            $head->addChild(new Template_TextNode($head_html));
        }
    }
?>