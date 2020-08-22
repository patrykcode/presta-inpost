<?php 


class InpostPdf {

    public $save_path = '';
    
    public function __construct(){
        $this->save_path = _PS_ROOT_DIR_._PS_IMG_ .'inpost_labels/';
    }

    public function getPath(){
        return $this->save_path ;
    }

    public function savePdfToFile($filename,$content,$save_path=false){
        $path = $this->save_path.$filename;

        if(!is_dir($this->save_path)){
            mkdir($this->save_path, 0777, true);
        }

        file_put_contents($path,$content);
        return $filename;
    }

    public function getFile($filename){
        $path = $this->getPath() . $filename;

        if(file_exists($path)){
            header('Content-type: application/pdf'); 
            header('Content-Disposition: inline; filename="' . $filename . '"');  
            header('Content-Transfer-Encoding: binary'); 
            header('Accept-Ranges: bytes'); 

            @readfile($path);
        }
    }

}