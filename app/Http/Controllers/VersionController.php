<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ObjLibrary\Version;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\Relationship\RelationshipManager;
use App\Http\Controllers\Revision\RevisionManager;
use App\Http\Controllers\ACL\ACL;

class VersionController extends Controller
{
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TVERSION";
    private $awsCognito;
    
    //Initailize constructor of schema controller
    public function __construct(Request $request){
        $this->data     =   $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me       =   new Version(Route::current()->parameter('tenantId'),
                                        Route::current()->parameter('proId'));
        $this->schema   =   new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Getting schema of po values in po builder of the program
     * @date 23-Nov-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  object $programObj
     * @return array
     */
    private function getSchemaInPOBuilder($programObj){
        $schemaInPOBuilder = array();
        foreach($programObj->pos['fields'] as $field){
            foreach($field['sections'] as $section){
                foreach($section['items'] as $item){
                    array_push($schemaInPOBuilder, $item);
                }
            }
        }
        return $schemaInPOBuilder;
    }
    /**
     * Save version of program object to database
     * @date 08-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $proId
     * @return newly created version object json
     */
    public function create($tenantId, $proId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $aclObj = new ACL($tenantId, $user, $this->schema);
            if(!$aclObj->canAddNew($this->data)){
                return ErrorHelper::getPermissionError();
            }
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            //Add pre-defined fields to input data
            $this->data["tenant"] = $tenantId;
            $this->data["programName"] = $proId;
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                $proObj = $this->me->getProgram($proId);
                if($proObj){
                    $findObj = $proObj->versions()->where('code',$this->data["code"])
                                    ->where(Version::FIELD_PROGRAM, $proObj->name)
                                    ->where(Version::FIELD_TENANT, $tenantId)->first();
                    if(isset($findObj)){
                        //Validate request with unique name of version
                        $validator = Validator::make($this->data,[
                            'code' => 'unique:versions,code'
                        ]);
                        if ($validator->fails()){
                            return ErrorHelper::getUniqueValidationError($validator->errors()->first('code'));
                        }
                    }
                    //Render input and save new version object
                    $saveObj = $this->schema->renderInput($this->data, $this->me->getModel(), $user, "C");
                    // HANDLE VALUE OF PROCESSING OPTION IN VERSION AGAINST SCHEMA DESIGNED IN PO BUILDER
                    if(isset($this->data['poValues'])){
                        $schemaInPOBuilder = $this->getSchemaInPOBuilder($proObj);
                        $poSchema = new SchemaRender();
                        $poSchema->setAllFields($schemaInPOBuilder);
                        $validateSMSPO = $poSchema->validate($this->data['poValues']);
                        if($validateSMSPO["status"]){
                            $saveObj->po_values = $poSchema->renderInput($this->data['poValues'], new \stdClass(), $user, "");
                        }else{
                            return ErrorHelper::getExceptionError($validateSMSPO["message"]);
                        }
                    }
                    // END OF VALUE OF PROCESSING OPTION
                    $outputObj  =   $this->me->save($saveObj);
                    //Saving value-relationship data
                    $relationshipObj = new RelationshipManager($tenantId);
                    $relationshipObj->save($this->schema->getAllFields(), 
                                            $this->data, 
                                            $outputObj, 
                                            $this->schema->getPluralName(), 
                                            $user);
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId .'/programs/'. $proId . '/versions/' . $outputObj->_id;
                    $result = $this->schema->renderOutput($outputObj, $href, $user);
                    if(isset($this->data['poValues'])){
                        $result["poValues"] = $poSchema->renderOutput($outputObj->po_values, "", $user);
                    }
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager($tenantId);
                    $eventId = $revisionObj->newEvent(
                                            'Event - Saving New Version', 
                                            $user
                                        );
                    $revisionObj->save($eventId,
                                        $this->schema->getAllFields(),
                                        null,
                                        $result,
                                        'Save',
                                        $this->schema->getPluralName(),
                                        $user);
                    return response()->json($result);
                }else{
                    return ErrorHelper::getNotFoundError("Program");
                }
            }else{
                return ErrorHelper::getExceptionError($validateSMS["message"]);
            }
        }catch (\InvalidArgumentException $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
        catch (\Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException $ex){
            return ErrorHelper::getExceptionError($ex->getAwsErrorMessage());
        }
        catch(\Firebase\JWT\ExpiredException $ex){
            return ErrorHelper::getExpiredAccessTokenError();
        }
        catch(\Exception $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
    }
    /**
     * Update version of program object to database
     * @date 27-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $proId
     * @param  string $versionId
     * @return updated version object json
     */
    public function update($tenantId, $proId, $versionId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            //Add pre-defined fields to input data
            $this->data["tenant"] = $tenantId;
            $this->data["programName"] = $proId;
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                $proObj = $this->me->getProgram($proId);
                if($proObj){
                    $criteria = new CriteriaOption();
                    $criteria->where(Version::FIELD_TENANT, $tenantId);
                    $criteria->where(Version::FIELD_PROGRAM, $proObj->name);
                    $criteria->whereRaw($this->me->getDefaultFilter($versionId),$this->schema->getAllFields(),$user);
                    $this->me->pushCriteria($criteria);
                    $this->me->applyCriteria();
                    $updateObj  =   $this->me->readOne($this->schema->getAttributes());
                    if($updateObj){
                        $aclObj = new ACL($tenantId, $user, $this->schema);
                        if(!$aclObj->canChange($updateObj)){
                            return ErrorHelper::getPermissionError();
                        }
                        $findObj = $proObj->versions()->where('code',$this->data["code"])
                                        ->where(Version::FIELD_PROGRAM, $proObj->name)
                                        ->where(Version::FIELD_TENANT, $tenantId)->first();
                        if($updateObj->code != $this->data["code"] && isset($findObj)){
                            //Validate request with unique name of version
                            $validator = Validator::make($this->data,[
                                'code' => 'unique:versions,code'
                            ]);
                            if ($validator->fails()){
                                return ErrorHelper::getUniqueValidationError($validator->errors()->first('code'));
                            }
                        }
                        //Get menu selection object by id for value-relationship
                        $oldObj = $this->me->readOne($this->schema->getAttributes());
                        $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId .'/programs/'. $proId . '/versions/' . $oldObj->_id;
                        $oldTargetObj = $this->schema->renderOutput($oldObj, $href, $user);
                        //Render input and update version object
                        $saveObj    =   $this->schema->renderInput($this->data, $updateObj, $user, "U");
                        // HANDLE VALUE OF PROCESSING OPTION IN VERSION AGAINST SCHEMA DESIGNED IN PO BUILDER
                        if(isset($this->data['poValues'])){
                            $schemaInPOBuilder = $this->getSchemaInPOBuilder($proObj);
                            $poSchema = new SchemaRender();
                            $poSchema->setAllFields($schemaInPOBuilder);
                            $validateSMSPO = $poSchema->validate($this->data['poValues']);
                            if($validateSMSPO["status"]){
                                $saveObj->po_values = $poSchema->renderInput($this->data['poValues'], new \stdClass(), $user, "");
                            }else{
                                return ErrorHelper::getExceptionError($validateSMSPO["message"]);
                            }
                        }
                        // END OF VALUE OF PROCESSING OPTION
                        $outputObj  =   $this->me->save($saveObj);
                        //Updating value-relationship data
                        $relationshipObj = new RelationshipManager($tenantId);
                        $relationshipObj->update($this->schema->getAllFields(), 
                                                $this->data, 
                                                $oldTargetObj, 
                                                $outputObj, 
                                                $this->schema->getPluralName(), 
                                                $user);
                        //Render output and return updated resource in json
                        $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId .'/programs/'. $proId . '/versions/' . $outputObj->_id;
                        $result = $this->schema->renderOutput($outputObj, $href, $user);
                        if(isset($this->data['poValues'])){
                            $result["poValues"] = $poSchema->renderOutput($outputObj->po_values, "", $user);
                        }
                        //Saving audit trail and data versioning
                        $revisionObj = new RevisionManager($tenantId);
                        $eventId = $revisionObj->newEvent(
                                                'Event - Modifying Version', 
                                                $user
                                            );
                        $revisionObj->save($eventId,
                                            $this->schema->getAllFields(),
                                            $oldTargetObj,
                                            $result,
                                            'Update',
                                            $this->schema->getPluralName(),
                                            $user);
                        return response()->json($result);
                    }else{
                        return ErrorHelper::getNotFoundError("Version");
                    }
                }else{
                    return ErrorHelper::getNotFoundError('Program');
                }
            }else{
                return ErrorHelper::getExceptionError($validateSMS["message"]);
            }
        }catch (\InvalidArgumentException $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
        catch (\Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException $ex){
            return ErrorHelper::getExceptionError($ex->getAwsErrorMessage());
        }
        catch(\Firebase\JWT\ExpiredException $ex){
            return ErrorHelper::getExpiredAccessTokenError();
        }
        catch(\Exception $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
    }
    /**
     * Get a version of program object from database
     * @date 16-May-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $proId
     * @param  string $versionCode
     * @return a version object json
     */
    public function readOne($tenantId, $proId, $versionCode){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $proObj = $this->me->getProgram($proId);
            if($proObj){
                $criteria = new CriteriaOption();
                $criteria->where(Version::FIELD_TENANT, $tenantId);
                $criteria->where(Version::FIELD_PROGRAM, $proObj->name);
                $criteria->whereRaw($this->me->getDefaultFilter($versionCode),$this->schema->getAllFields(), $user);
                $this->me->pushCriteria($criteria);
                $this->me->applyCriteria();
                $returnObjDS = $this->me->readOne();
                if($returnObjDS){
                    $aclObj = new ACL($tenantId, $user, $this->schema);
                    if(!$aclObj->canRead($returnObjDS)){
                        return ErrorHelper::getPermissionError();
                    }
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId .'/programs/'. $proId . '/versions/' . $returnObjDS->_id;
                    $arrVersion = $this->schema->renderOutput($returnObjDS, $href, $user);
                    if(isset($this->data['expand']) && $this->data['expand'] == 'program'){
                        $proSchema = new SchemaRender("TPROGRAMOBJECT");
                        $proHref = env('APP_URL') .env('APP_VERSION').'/programs/'. $proId;
                        $arrVersion["program"] = $proSchema->renderOutput($proObj, $proHref, $user);
                    }
                    // HANDLE VALUE OF PROCESSING OPTION IN VERSION AGAINST SCHEMA DESIGNED IN PO BUILDER
                    if(isset($returnObjDS->po_values)){
                        $schemaInPOBuilder = $this->getSchemaInPOBuilder($proObj);
                        $poSchema = new SchemaRender();
                        $poSchema->setAllFields($schemaInPOBuilder);
                        $arrVersion["poValues"] = $poSchema->renderOutput((object)$returnObjDS->po_values, "", $user);
                    }else if($returnObjDS->po_values == null){
                        $arrVersion["poValues"] = null;
                    }
                    // END OF VALUE OF PROCESSING OPTION
                    return response()->json($arrVersion);
                }else{
                    return ErrorHelper::getNotFoundError('Version');
                }
            }else{
                return ErrorHelper::getNotFoundError('Program');
            }
        }catch (\InvalidArgumentException $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
        catch (\Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException $ex){
            return ErrorHelper::getExceptionError($ex->getAwsErrorMessage());
        }
        catch(\Firebase\JWT\ExpiredException $ex){
            return ErrorHelper::getExpiredAccessTokenError();
        }
        catch(\Exception $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
    }
    /**
     * Delete version of program object to database
     * @date 29-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $proId
     * @param  string $versionId
     * @return -
     */
    public function remove($tenantId, $proId, $versionId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $proObj = $this->me->getProgram($proId);
            if($proObj){
                $criteria = new CriteriaOption();
                $criteria->where(Version::FIELD_TENANT, $tenantId);
                $criteria->where(Version::FIELD_PROGRAM, $proObj->name);
                $criteria->whereRaw($this->me->getDefaultFilter($versionId),$this->schema->getAllFields(), $user);
                $this->me->pushCriteria($criteria);
                $this->me->applyCriteria();
                $targetObj = $this->me->readOne($this->schema->getAttributes());
                if($targetObj){
                    $aclObj = new ACL($tenantId, $user, $this->schema);
                    if(!$aclObj->canDelete($targetObj)){
                        return ErrorHelper::getPermissionError();
                    }
                    $relationshipObj = new RelationshipManager($tenantId);
                    if(!$relationshipObj->inUsed($targetObj, $this->schema->getPluralName())){
                        $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId .'/programs/'. $proId . '/versions/' . $targetObj->_id;
                        $tObj = $this->schema->renderOutput($targetObj, $href, $user);
                        $relationshipObj->remove($this->schema->getAllFields(), 
                                                    $tObj, 
                                                    $this->schema->getPluralName()
                                                );
                        //Saving audit trail and data versioning
                        $revisionObj = new RevisionManager($tenantId);
                        $eventId = $revisionObj->newEvent(
                                                'Event - Deleting Version', 
                                                $user
                                            );
                        $revisionData[] = $revisionObj->getRevision($eventId, 
                                                $this->schema->getPluralName(), 
                                                $versionId, 
                                                null, 
                                                null, 
                                                null, 
                                                'Delete');
                        $revisionObj->insertData($revisionData, $user);
                        if($this->me->remove()){ return Response()->json(array())->setStatusCode(200); }
                    }else{
                        return ErrorHelper::getInUsedError("Version");
                    }
                }else{
                    return ErrorHelper::getNotFoundError("Version");
                }
            }else{
                return ErrorHelper::getNotFoundError('Program');
            }
        }catch (\InvalidArgumentException $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
        catch (\Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException $ex){
            return ErrorHelper::getExceptionError($ex->getAwsErrorMessage());
        }
        catch(\Firebase\JWT\ExpiredException $ex){
            return ErrorHelper::getExpiredAccessTokenError();
        }
        catch(\Exception $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
    }
    /**
     * list all versions of object library from database
     * @date 10-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $proId
     * @return list of version json
     */
    public function readAll($tenantId, $proId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $programObj = $this->me->getProgram($proId);
            if($programObj){
                $criteria   =   new CriteriaOption();
                $criteria->where(Version::FIELD_TENANT, $tenantId);
                $criteria->where(Version::FIELD_PROGRAM, $programObj->name);
                $aclObj = new ACL($tenantId, $user, $this->schema);
                $aclFilters = $aclObj->getFiltersOfACL();
                if(count($aclFilters) > 0 ){
                    $criteria->whereRaw($aclFilters,$this->schema->getAllFields(),$user);
                }
                if(isset($this->data['filter'])){
                    $criteria->whereRaw($this->data['filter'],$this->schema->getAllFields(),$user);
                }
                $this->me->pushCriteria($criteria);
                $this->me->applyCriteria();
                $recordSize =   $this->me->count();
                if(isset($this->data['offset'])){
                    $offset = $criteria->offset($this->data['offset']);
                }else{
                    $offset = $criteria->offset();
                }
                if(isset($this->data['limit'])){
                    $limit = $criteria->limit($this->data['limit']);
                }else{
                    $limit = $criteria->limit();
                }
                $this->me->pushCriteria($criteria);
                $this->me->applyCriteria();
                $ds =   $this->me->readAll();
                $dsHref = env("APP_URL").env("APP_VERSION").'/tenants/'. $tenantId  . "/programs/" . $proId . "/versions";
                if(isset($this->data['expand']) && $this->data['expand'] == 'program'){
                    $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user, "program", "TPROGRAMOBJECT","", $programObj);
                }else{
                    $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user);
                }
                // HANDLE VALUE OF PROCESSING OPTION IN VERSION AGAINST SCHEMA DESIGNED IN PO BUILDER
                $ind = 0;
                foreach($resultSet['items'] as $item){
                    foreach($ds as $datasource){
                        if($datasource->code == $item['code']){
                            if(isset($datasource->po_values)){
                                $schemaInPOBuilder = $this->getSchemaInPOBuilder($programObj);
                                $poSchema = new SchemaRender();
                                $poSchema->setAllFields($schemaInPOBuilder);
                                $resultSet['items'][$ind]["poValues"] = $poSchema->renderOutput((object)$datasource->po_values, "", $user);
                            }else if($datasource->po_values == null){
                                $resultSet['items'][$ind]["poValues"] = null;
                            }
                        }
                    }
                    $ind++;
                }
                // END OF VALUE OF PROCESSING OPTION
                return response()->json($resultSet);
            }else{
                return ErrorHelper::getNotFoundError('Program');
            }
        }catch (\InvalidArgumentException $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
        catch (\Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException $ex){
            return ErrorHelper::getExceptionError($ex->getAwsErrorMessage());
        }
        catch(\Firebase\JWT\ExpiredException $ex){
            return ErrorHelper::getExpiredAccessTokenError();
        }
        catch(\Exception $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
    }
}
