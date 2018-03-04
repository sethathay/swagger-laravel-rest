<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\File;

use App\Http\Controllers\File\File;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\Relationship\RelationshipManager;

class FileManager {
    
    private $tenantId;
    private $schemaFile;
    
    public function __construct($tenantId = null) {
        $this->schemaFile = "TFILE";
        if($tenantId != null){
            $this->tenantId = $tenantId;
        }
    }
    
    public function getTenantId(){
        return $this->tenantId;
    }
    
    public function save($files, $resource, $resourceId, $user){
        $fileIds = array();
        $fileObj = new File($this->getTenantId());
        $fileList = $fileObj->moveFiles($files);
        foreach($fileList as $f){
            $data = array(
                'tenant' => $this->getTenantId(),
                'path' => $f["path"],
                'name' => $f["name"],
                'resource' => $resource,
                'resourceId' => $resourceId
            );
            $fObj = new File($this->getTenantId());
            $schemaFile = new SchemaRender($this->schemaFile);
            $dataFile = $schemaFile->renderInput($data, $fObj->getModel(), $user, "C");
            $outputFile = $fObj->save($dataFile);
            $fileIds[] = $outputFile->_id;
            //Saving value-relationship data
            $relationshipObj = new RelationshipManager($this->getTenantId());
            $relationshipObj->save($schemaFile->getAllFields(), 
                                    $data, 
                                    $outputFile, 
                                    $schemaFile->getPluralName(), 
                                    $user);
            //Relationship of resource with file
            $relData[] = $relationshipObj->getRelationship(
                                $schemaFile->getPluralName(), 
                                $outputFile->_id, 
                                "resource_id", 
                                $resource, 
                                $resourceId, 
                                "_id");
            $relationshipObj->insertData($relData, $user);
        }
        return $fileIds;
    }
    
}
