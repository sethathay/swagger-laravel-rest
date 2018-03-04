<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\File;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;

class File extends AbstractResourceProvider{
    
    private $tenantId;
    
    public function __construct($tenantId = null) {
        parent::__construct();
        if($tenantId != null){
            $this->tenantId = $tenantId;
        }
    }
    
    public function getTenantId(){
        return $this->tenantId;
    }
    
    //Override abstract method model of abstract resource provider
    public function model(){
        return 'App\File';
    }
    
    /**
     * Save attachment file to AWS S3
     * @date 25-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param array $files attachment files user attach in post
     * @return string
     */
    public function moveFiles($files){
        $fileData = array_values($files);
        // Move files from temporary to main folder of each tenant
        $s3 =   \Storage::disk('s3');
        foreach ($files as $file){
             $oldfilepathAndName = env('S3_BUCKETFOLDER_TEMP_ATTACHMENTS').$file['path'];
             $newfilepathAndName = env('S3_BUCKETFOLDER_ATTACHMENTS').$file['path'];
             $s3->move($oldfilepathAndName, $newfilepathAndName, 'public');
        }
        return $fileData;
    }
}
