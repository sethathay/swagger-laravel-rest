<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Udc\UdcFoundation;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\Relationship\RelationshipManager;
use App\Http\Controllers\Revision\RevisionManager;
use App\Http\Controllers\ACL\ACL;

class UdcTypeFoundationController extends Controller
{
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TUDCTYPEFOUNDATION";
    private $awsCognito;
    
    //Initailize constructor of udc type controller
    public function __construct(Request $request){
        $this->data = $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me = new UdcFoundation(Route::current()->parameter('sysCode'));
        $this->schema = new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Save udc type of foundation to database
     * @date 17-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $sysCode
     * @return newly created json of udc type foundation
     */
    public function create($sysCode){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $aclObj = new ACL(null, $user, $this->schema);
            if(!$aclObj->canAddNew($this->data)){
                return ErrorHelper::getPermissionError();
            }
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            if(isset($this->data["recordType"])){ unset($this->data["recordType"]); }
            //Add pre-defined fields to input data
            $this->data["sysCode"] = $this->me->getSysCode();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                $criteria = new CriteriaOption();
                $uTypeObj = new UdcFoundation($sysCode);
                $criteria->where(UdcFoundation::FIELD_RECORD_TYPE, $uTypeObj->getRecordType());
                $criteria->where(UdcFoundation::FIELD_SYS_CODE, $uTypeObj->getSysCode());
                $criteria->where('code', $this->data['code']);
                $uTypeObj->pushCriteria($criteria);
                $uTypeObj->applyCriteria();
                $findObj = $uTypeObj->readOne();
                if(isset($findObj)){
                    //Validate request with unique code of udc type
                    $validator = Validator::make($this->data,[
                        'code'      =>  'unique:weCloudFoundation.udcs,code'
                    ]);
                    if ($validator->fails()){
                        return ErrorHelper::getUniqueValidationError($validator->errors()->first('code'));
                    }
                }
                //Render input and save new udc type object
                $obj = $this->schema->renderInput($this->data, $this->me->getModel(), $user, "C");
                $outputObj = $this->me->save($obj);
                //Query to get recently added item (Reason: key of udc type is code not id)
                $ct = new CriteriaOption();
                $udcTypeOb = new UdcFoundation($sysCode);
                $ct->where(UdcFoundation::FIELD_RECORD_TYPE, $udcTypeOb->getRecordType());
                $ct->where(UdcFoundation::FIELD_SYS_CODE, $udcTypeOb->getSysCode());
                $ct->where('code', $outputObj->getKey());
                $udcTypeOb->pushCriteria($ct);
                $udcTypeOb->applyCriteria();
                $result = $udcTypeOb->readOne();
                //Saving value-relationship data
                $relationshipObj = new RelationshipManager();
                $relationshipObj->save($this->schema->getAllFields(), 
                                        $this->data, 
                                        $result, 
                                        $this->schema->getPluralName(), 
                                        $user);
                //Render output and return created resource in json
                $href = env('APP_URL') .env('APP_VERSION').'/systems/'. $sysCode . '/types/'. $result->_id;
                $arrOutput = $this->schema->renderOutput($result, $href, $user);
                //Saving audit trail and data versioning
                $revisionObj = new RevisionManager();
                $eventId = $revisionObj->newEvent(
                                        'Event - Saving New Udc Type Foundation', 
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
     * Update basic information of udc type foundation to database
     * @date 17-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $sysCode
     * @param  string $typeId
     * @return updated udc type foundation object json
     */
    public function update($sysCode,$typeId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            if(isset($this->data["recordType"])){ unset($this->data["recordType"]); }
            //Add pre-defined fields to input data
            $this->data["sysCode"] = $this->me->getSysCode();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Getting udc type by sys code and id
                $criteria = new CriteriaOption();
                $uTypeObj = new UdcFoundation($sysCode);
                $criteria->where(UdcFoundation::FIELD_RECORD_TYPE, $uTypeObj->getRecordType());
                $criteria->where(UdcFoundation::FIELD_SYS_CODE, $uTypeObj->getSysCode());
                $criteria->whereRaw($uTypeObj->getDefaultFilter($typeId),$this->schema->getAllFields(),$user);
                $uTypeObj->pushCriteria($criteria);
                $uTypeObj->applyCriteria();
                $updatedObj = $uTypeObj->readOne($this->schema->getAttributes());
                if($updatedObj){
                    $aclObj = new ACL(null, $user, $this->schema);
                    if(!$aclObj->canChange($updatedObj)){
                        return ErrorHelper::getPermissionError();
                    }
                    if(isset($updatedObj) && $updatedObj->code != $this->data['code']){
                        //Validate request with unique code of udc type
                        $validator = Validator::make($this->data,[
                            'code'      =>  'unique:weCloudFoundation.udcs,code'
                        ]);
                        if ($validator->fails()){
                            return ErrorHelper::getUniqueValidationError($validator->errors()->first('code'));
                        }
                    }
                    //Get udc type object by id for value-relationship
                    $oldObj = $uTypeObj->readOne($this->schema->getAttributes());
                    $href = env('APP_URL') .env('APP_VERSION'). '/systems/'. $sysCode . '/types/'. $oldObj->_id;
                    $oldTargetObj = $this->schema->renderOutput($oldObj, $href, $user);
                    //Render input and update udc type object
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
                    $href = env('APP_URL') .env('APP_VERSION').'/systems/'. $sysCode . '/types/'. $outputObj->_id;
                    $result = $this->schema->renderOutput($outputObj,$href,$user);
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager();
                    $eventId = $revisionObj->newEvent(
                                            'Event - Modifying Udc Type Foundation', 
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
                    return ErrorHelper::getNotFoundError("Udc Type");
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
     * list all information of udc type foundation from database
     * @date 17-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $sysCode
     * @return list of udc type foundation json
     */
    public function readAll($sysCode){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria   =   new CriteriaOption();
            $criteria->where(UdcFoundation::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(UdcFoundation::FIELD_SYS_CODE, $sysCode);
            $aclObj = new ACL(null, $user, $this->schema);
            $aclFilters = $aclObj->getFiltersOfACL();
            if(count($aclFilters) > 0 ){
                $criteria->whereRaw($aclFilters,$this->schema->getAllFields(),$user);
            }
            if(isset($this->data['filter'])){
                $criteria->whereRaw($this->data['filter'],$this->schema->getAllFields(),$user);
            }
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $recordSize = $this->me->count();
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
            $dsHref = env("APP_URL").env("APP_VERSION") . "/systems/" . $sysCode . "/types";
            if(isset($this->data['expand']) && $this->data['expand'] == 'values'){
                $ds = $this->me->readAll();
                $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user, "values", "TUDCVALUEFOUNDATION", "values");
            }else{
                $ds = $this->me->readAll($this->schema->getAttributes());
                $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user);
            }
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
     * list one udc type in collection udcs from database
     * @date 17-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $sysCode
     * @param  string $typeId
     * @return A udc type foundation json
     */
    public function readOne($sysCode,$typeId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(UdcFoundation::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(UdcFoundation::FIELD_SYS_CODE, $this->me->getSysCode());
            $criteria->whereRaw($this->me->getDefaultFilter($typeId),$this->schema->getAllFields(),$user);
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            //Read all but return only first found result
            if(isset($this->data['expand']) && $this->data['expand'] == 'values'){
                $updatedObj = $this->me->readOne();
                $aclObj = new ACL(null, $user, $this->schema);
                if(!$aclObj->canRead($updatedObj)){
                    return ErrorHelper::getPermissionError();
                }
                $href = env('APP_URL') .env('APP_VERSION').'/systems/'. $sysCode . '/types/'. $updatedObj->_id;
                $arrType = $this->schema->renderOutput($updatedObj,$href,$user);
                $valueHref = $href . "/" . "values";
                $ct = new CriteriaOption();
                $arrType['values'] = $this->schema->renderOutputDS(
                        new SchemaRender("TUDCVALUEFOUNDATION"), $updatedObj->values,
                        $valueHref, count($updatedObj->values), $ct->offset(), $ct->limit(), $user);
            }else{
                $updatedObj = $this->me->readOne($this->schema->getAttributes());
                $aclObj = new ACL($tenantId, $user, $this->schema);
                if(!$aclObj->canRead($updatedObj)){
                    return ErrorHelper::getPermissionError();
                }
                $href = env('APP_URL') .env('APP_VERSION').'/systems/'. $sysCode . '/types/'. $updatedObj->_id;
                $arrType = $this->schema->renderOutput($updatedObj,$href,$user);
            }
            return response()->json($arrType);
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
     * Delete udc type foundation object from database
     * @date 17-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $sysCode
     * @param  string $typeId
     * @return -
     */
    public function remove($sysCode,$typeId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(UdcFoundation::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(UdcFoundation::FIELD_SYS_CODE,$sysCode);
            $criteria->whereRaw($this->me->getDefaultFilter($typeId),$this->schema->getAllFields(),$user);
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
                    $href = env('APP_URL') .env('APP_VERSION') . '/systems/'. $sysCode . '/types/'. $targetObj->_id;
                    $tObj = $this->schema->renderOutput($targetObj, $href, $user);
                    $relationshipObj->remove($this->schema->getAllFields(), 
                                                $tObj, 
                                                $this->schema->getPluralName()
                                            );
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager();
                    $eventId = $revisionObj->newEvent(
                                            'Event - Deleting Udc Type Foundation', 
                                            $user
                                        );
                    $revisionData[] = $revisionObj->getRevision($eventId, 
                                            $this->schema->getPluralName(), 
                                            $typeId, 
                                            null, 
                                            null, 
                                            null, 
                                            'Delete');
                    $revisionObj->insertData($revisionData, $user);
                    if($this->me->remove()){ return Response()->json(array())->setStatusCode(200); }
                }else{
                    return ErrorHelper::getInUsedError("Udc Type");
                }
            }else{
                return ErrorHelper::getNotFoundError("Udc Type");
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