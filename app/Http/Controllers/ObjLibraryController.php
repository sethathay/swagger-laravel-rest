<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ObjLibrary\ObjLibrary;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\Relationship\RelationshipManager;
use App\Http\Controllers\Revision\RevisionManager;
use App\Http\Controllers\ACL\ACL;

class ObjLibraryController extends Controller
{
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TOBJECTLIBRARY";
    private $awsCognito;
    
    //Initailize constructor of object library controller
    public function __construct(Request $request){
        $this->data = $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me = new ObjLibrary();
        $this->schema = new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Saveing new object library resource
     * @date 05-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return newly created object library json
     */
    public function create(){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $aclObj = new ACL(null, $user, $this->schema);
            if(!$aclObj->canAddNew($this->data)){
                return ErrorHelper::getPermissionError();
            }
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            if(isset($this->data["recordType"])){ unset($this->data["recordType"]); }
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Validate request with unique name of object library
                $validator = Validator::make($this->data,[
                    'name' => 'unique:obj_libraries,name'
                ]);
                if ($validator->fails()){
                    return ErrorHelper::getUniqueValidationError($validator->errors()->first('name'));
                }
                //Render input and save new object library object
                $obj = $this->schema->renderInput($this->data, $this->me->getModel(), $user, "C");
                $outputObj = $this->me->save($obj);
                //Query to get recently added item (Reason: key of object library is name not id)
                $ct = new CriteriaOption();
                $obLibrary = new ObjLibrary();
                $ct->where(ObjLibrary::FIELD_RECORD_TYPE, $obLibrary->getRecordType());
                $ct->where('name', $outputObj->getKey());
                $obLibrary->pushCriteria($ct);
                $obLibrary->applyCriteria();
                $result = $obLibrary->readOne();
                //Saving value-relationship data
                $relationshipObj = new RelationshipManager();
                $relationshipObj->save($this->schema->getAllFields(), 
                                        $this->data, 
                                        $result, 
                                        $this->schema->getPluralName(), 
                                        $user);
                //Render output and return created resource in json
                $href = env('APP_URL') .env('APP_VERSION').'/objectlibraries/'. $result->_id;
                $arrOutput = $this->schema->renderOutput($result, $href, $user);
                //Saving audit trail and data versioning
                $revisionObj = new RevisionManager();
                $eventId = $revisionObj->newEvent(
                                        'Event - Saving New Object Library', 
                                        $user
                                    );
                $revisionObj->save($eventId,
                                    $this->schema->getAllFields(),
                                    null,
                                    $arrOutput,
                                    'Save',
                                    $this->schema->getPluralName(),
                                    $user);
                return response()->json($arrOutput);
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
     * Updating a object library resource by id
     * @date 05-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $objId
     * @return updated object library json
     */
    public function update($objId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            if(isset($this->data["recordType"])){ unset($this->data["recordType"]); }
            if(isset($this->data["type"])){ unset($this->data["type"]); }
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Get object library object by id for updating
                $criteria = new CriteriaOption();
                $criteria->where(ObjLibrary::FIELD_RECORD_TYPE,$this->me->getRecordType());
                $criteria->whereRaw($this->me->getDefaultFilter($objId),$this->schema->getAllFields(),$user);
                $this->me->pushCriteria($criteria);
                $this->me->applyCriteria();
                $updatedObj = $this->me->readOne($this->schema->getAttributes());
                if($updatedObj){
                    $aclObj = new ACL(null, $user, $this->schema);
                    if(!$aclObj->canChange($updatedObj)){
                        return ErrorHelper::getPermissionError();
                    }
                    //Validate request with unique name of object library
                    $validator = Validator::make($this->data,[
                        'name' => 'unique:obj_libraries,name,' . $updatedObj->id . ',_id'
                    ]);
                    if ($validator->fails()){
                        return ErrorHelper::getUniqueValidationError($validator->errors()->first('name'));
                    }
                    //Get object library by id for value-relationship
                    $oldObj = $this->me->readOne($this->schema->getAttributes());
                    $href = env('APP_URL') .env('APP_VERSION').'/objectlibraries/'. $oldObj->_id;
                    $oldTargetObj = $this->schema->renderOutput($oldObj, $href, $user);
                    //Render input and update object library object
                    $obj = $this->schema->renderInput($this->data, $updatedObj, $user, "U");
                    $outputObj = $this->me->save($obj);
                    //Updating value-relationship data
                    $relationshipObj = new RelationshipManager();
                    $relationshipObj->update($this->schema->getAllFields(), 
                                            $this->data, 
                                            $oldTargetObj, 
                                            $outputObj, 
                                            $this->schema->getPluralName(), 
                                            $user);
                    //Render output and return updated resource in json
                    $href = env('APP_URL') .env('APP_VERSION').'/objectlibraries/'. $outputObj->_id;
                    $result = $this->schema->renderOutput($outputObj, $href, $user);
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager();
                    $eventId = $revisionObj->newEvent(
                                            'Event - Modifying Object Library', 
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
                    return ErrorHelper::getNotFoundError('Object Library');
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
     * list all basic information of object library from database
     * @date 07-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return list of object library json
     */
    public function readAll(){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria   =   new CriteriaOption();
            $criteria->where(ObjLibrary::FIELD_RECORD_TYPE,$this->me->getRecordType());
            $aclObj = new ACL(null, $user, $this->schema);
            $aclFilters = $aclObj->getFiltersOfACL();
            if(count($aclFilters) > 0 ){
                $criteria->whereRaw($aclFilters, $this->schema->getAllFields(), $user);
            }
            if(isset($this->data['filter'])){
                $criteria->whereRaw($this->data['filter'],$this->schema->getAllFields(),$user);
            }
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $recordSize = $this->me->count();
            if(isset($this->data['offset'])){
                $offset     =   $criteria->offset($this->data['offset']);
            }else{
                $offset     =   $criteria->offset();
            }
            if(isset($this->data['limit'])){
                $limit      =   $criteria->limit($this->data['limit']);
            }else{
                $limit      =   $criteria->limit();
            }
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $ds =   $this->me->readAll($this->schema->getAttributes());
            $href = env("APP_URL").env("APP_VERSION") . "/objectlibraries";            
            $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $href, $recordSize, $offset, $limit, $user);
            return response()->json($resultSet);
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
     * list one of basic information of object library
     * @date 12-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return list a object library json
     */
    public function readOne($objId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(ObjLibrary::FIELD_RECORD_TYPE,$this->me->getRecordType());
            $criteria->whereRaw($this->me->getDefaultFilter($objId),$this->schema->getAllFields(),$user);
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $result = $this->me->readOne($this->schema->getAttributes());
            if($result){
                $aclObj = new ACL(null, $user, $this->schema);
                if(!$aclObj->canRead($result)){
                    return ErrorHelper::getPermissionError();
                }
                $href = env('APP_URL') .env('APP_VERSION').'/objectlibraries/' . $result->_id;
                return response()->json($this->schema->renderOutput($result, $href, $user));
            }else{
                return ErrorHelper::getNotFoundError('Object Library');
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
     * Delete a object library from database
     * @date 12-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $objId
     * @return -
     */
    public function remove($objId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(ObjLibrary::FIELD_RECORD_TYPE,$this->me->getRecordType());
            $criteria->whereRaw($this->me->getDefaultFilter($objId),$this->schema->getAllFields(),$user);
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $targetObj = $this->me->readOne($this->schema->getAttributes());
            if($targetObj){
                $aclObj = new ACL(null, $user, $this->schema);
                if(!$aclObj->canDelete($targetObj)){
                    return ErrorHelper::getPermissionError();
                }
                $relationshipObj = new RelationshipManager();
                if(!$relationshipObj->inUsed($targetObj, $this->schema->getPluralName())){
                    $href = env('APP_URL') .env('APP_VERSION').'/objectlibraries/' . $targetObj->_id;
                    $tObj = $this->schema->renderOutput($targetObj, $href, $user);
                    $relationshipObj->remove($this->schema->getAllFields(), 
                                                $tObj, 
                                                $this->schema->getPluralName()
                                            );
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager();
                    $eventId = $revisionObj->newEvent(
                                            'Event - Deleting Object Library', 
                                            $user
                                        );
                    $revisionData[] = $revisionObj->getRevision($eventId, 
                                            $this->schema->getPluralName(), 
                                            $objId, 
                                            null, 
                                            null, 
                                            null, 
                                            'Delete');
                    $revisionObj->insertData($revisionData, $user);
                    if($this->me->remove()){ return Response()->json(array())->setStatusCode(200); }
                }else{
                    return ErrorHelper::getInUsedError("Object Library");
                }
            }else{
                return ErrorHelper::getNotFoundError("Object Library");
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
