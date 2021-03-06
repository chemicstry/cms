<?
    class Plugin_input_string {
        public function __construct() {
            // Load JS
            Editor::LoadJS('_cms/plugins/cms_string/input_plugins/input_string/input_string.js');
        }
        
        public function GetContent($a_attr) {
            $string_id = $a_attr['id'];
            $data = Locales::ReadData($string_id);
            return $data['text'][Locales::GetLocale()];
        }
        
        public function GenEditorData($a_attr) {
            $data = array();
            $data['ownerid'] = $a_attr['ownerid'];
            $data['type'] = "input_string";
            $data['name'] = $a_attr['name'];
            $data['width'] = $a_attr['width'];
            $data['tooltip'] = Locales::getStringOrJSONLocale($a_attr['tooltip']);
            $data['title'] = Locales::getStringOrJSONLocale($a_attr['title']);
            $data['datepicker'] = (isset($a_attr['datepicker']) ? true : false);
            
            $locdata = Locales::ReadData($a_attr['id']);
            $data['locales'] = $locdata['text'];
            
            Editor::AddData(DATA_MODULE_DATA, $data);
        }
        
        public function SaveObject($a_data) {
            $object = $a_data->object;
            
            Locales::WriteData($a_data->data_id, array('text' => $object['locales']));
        }
    }
?>