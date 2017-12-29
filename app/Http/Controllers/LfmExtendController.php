<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Unisharp\Laravelfilemanager\Events\ImageIsUploading;
use Unisharp\Laravelfilemanager\Events\ImageWasUploaded;

use Unisharp\Laravelfilemanager\controllers\LfmController as LfmController;
/**
 * Class CreateController.
 */
class LfmExtendController extends LfmController
{
    protected $errors;

	
    public function __construct()
    {
        parent::__construct();
        $this->errors = [];
    }

	//-- Override ItemsController@getItems -------------------------------
	public function getItems()
    {
        $path = parent::getCurrentPath();
        $sort_type = request('sort_type');

        $files = parent::sortFilesAndDirectories(parent::getFilesWithInfo($path), $sort_type);
        $directories = parent::sortFilesAndDirectories(parent::getDirectories($path), $sort_type);
        
        $paths_arr = array('');
        $types_arr = array('folder');
        $names_arr = array('..');
        foreach($directories as $f){ 
			array_push($paths_arr, $f->path); 
			array_push($types_arr, 'folder');
			array_push($names_arr, $f->name);
		}
        foreach($files as $f){ 
			array_push($paths_arr, $f->url); 
			array_push($types_arr, 'file');
			array_push($names_arr, $f->name);
		}
        
        return [ 'html' =>'Some text',
                'entries'   => $names_arr,
                'entrytype' => $types_arr,
                'paths' => $paths_arr,
            'working_dir' => parent::getInternalPath($path),
        ];
    }
    
    //-- Extend ----------------------------------------------------------
    
    public function create()
    {
        $filename = request()->file_name;
        $filetext = request()->file_text;
        //$filename = 'test_create';
		$filename = $this->getNewName($filename);
        $new_file_path = parent::getCurrentPath($filename);

        // single file
	
		if(!File::exists($new_file_path)) {
			// path does not exist

			if (!$this->proceedSingleUpload($new_file_path, $filetext)) {
				return $this->errors;
			}
			return redirect()->back();
		}else{
			
			return redirect()->back();
		}
        
		//return redirect()->back();
         
    }
    
    public function update()
    {
        $filename = request()->file_name;
        $filetext = request()->file_text;
        $new_file_path = parent::getCurrentPath($filename);

		if (!$this->proceedSingleUpload($new_file_path, $filetext)) {
			return $this->errors;
		}
		return redirect()->back();
         
    }
    
    public function copy()
    {
        //$filename = request()->file_name;
        $old_path = request()->copy_path;
        $filename = substr($old_path, strrpos($old_path, '/')+1);
        $new_path = parent::getCurrentPath($filename);

		if (File::isDirectory($old_path)){
			$ending = '';
			$i = strlen($new_path);
		}else{
			$i = strrpos($new_path,'.');
			$ending = substr($new_path, $i);
		}
		$k=1;
		$path_final = $new_path;
		while (File::exists($path_final)){
			$path_final = substr($new_path, 0, $i).'('.$k.')'.$ending;
			$k+=1;
		}
		$new_path = $path_final;
		$msg = $new_path.' | '.$old_path.' | '.substr($old_path, 0, $i);
		
		
		if(!File::exists($new_path)) {
			
			if (File::isDirectory($old_path)){
				if (!File::copyDirectory($old_path, $new_path)) {
					$msg = $msg.' Error ';
					return $this->errors;
				}
			}else{
				if (!File::copy($old_path, $new_path)) {
					$msg = $msg.' Error ';
					return $this->errors;
				}
			}
		}
        
		return redirect()->back() ->with(['msg'=>$msg]);
         
    }

    private function proceedSingleUpload($new_file_path, $content)
    {
        event(new ImageIsUploading($new_file_path));
        try {
  
			File::put($new_file_path, $content);
            
            chmod($new_file_path, config('lfm.create_file_mode', 0644));
        } catch (\Exception $e) {
            array_push($this->errors, parent::error('invalid'));
            return false;
        }

        event(new ImageWasUploaded(realpath($new_file_path)));

        return true;
    }



    private function getNewName($filename)
    {
		$new_filename = preg_replace('/[^A-Za-z0-9\-\']/', '_', $filename);
		return $new_filename.'.txt';
    }


}
