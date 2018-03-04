<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Udc\UdcValue;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\Relationship\RelationshipManager;
use App\Http\Controllers\Revision\RevisionManager;
use App\Http\Controllers\ACL\ACL;

class UdcValueController extends Controller
{
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TUDCVALUE";
    private $awsCognito;
    
    //Initailize constructor of udc value controller
    public function __construct(Request $request){
        $this->data = $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me = new UdcValue(Route::current()->parameter('tenantId'),
                                 Route::current()->parameter('sysCode'),
                                 Route::current()->parameter('typeId'));
        $this->schema = new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Save udc value of project to database
     * @date 17-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $sysCode
     * @param  string $typeId
     * @return newly created json of udc value project
     */
    public function create($tenantId,$sysCode,$typeId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $aclObj = new ACL($tenantId, $user, $this->schema);
            if(!$aclObj->canAddNew($this->data)){
                return ErrorHelper::getPermissionError();
            }
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            if(isset($this->data["recordType"])){ unset($this->data["recordType"]); }
            //Add pre-defined fields to input data
            $this->data["tenant"] = $this->me->getTenantId();
            $this->data["sysCode"] = $this->me->getSysCode();
            $this->data["udcCode"] = $this->me->getTypeId();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                $udcObj = $this->me->getUdc($tenantId, $sysCode, $typeId);
                if($udcObj){
                    $findObj = $udcObj->values()
                                ->where(UdcValue::FIELD_RECORD_TYPE, $this->me->getRecordType())
                                ->where(UdcValue::FIELD_TENANT, $tenantId)
                                ->where(UdcValue::FIELD_SYS_CODE, $sysCode)
                                ->where(UdcValue::FIELD_UDC, $udcObj->code)
                                ->where('code', $this->data["code"])->first();
                    if(isset($findObj)){
                        //Validate request with unique code of udc value
                        $validator = Validator::make($this->data,[
                            'code'      =>  'unique:udcs,code'
                        ]);
                        if($validator->fails()){
                            return ErrorHelper::getUniqueValidationError($validator->errors()->first('code'));
                        }
                    }
                    //Render input and save new udc value object
                    $obj = $this->schema->renderInput($this->data, $this->me->getModel(), $user, "C");
                    $outputObj = $this->me->save($obj);
                    //Query to get recently added item (Reason: key of udc value is code not id)
                    $ct = new CriteriaOption();
                    $udcValueObj = new UdcValue($tenantId, $sysCode, $typeId);
                    $ct->where(UdcValue::FIELD_RECORD_TYPE, $udcValueObj->getRecordType());
                    $ct->where(UdcValue::FIELD_TENANT, $udcValueObj->getTenantId());
                    $ct->where(UdcValue::FIELD_SYS_CODE, $udcValueObj->getSysCode());
                    $ct->where(UdcValue::FIELD_UDC, $udcObj->code);
                    $ct->where('code', $outputObj->getKey());
                    $udcValueObj->pushCriteria($ct);
                    $udcValueObj->applyCriteria();
                    $result = $udcValueObj->readOne();
                    //Saving value-relationship data
                    $relationshipObj = new RelationshipManager($tenantId);
                    $relationshipObj->save($this->schema->getAllFields(), 
                                            $this->data, 
                                            $result, 
                                            $this->schema->getPluralName(), 
                                            $user);
                    //Render output and return created resource in json
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/' . $tenantId . '/systems/'. $sysCode . '/types/' . $typeId . '/values/'. $result->_id;
                    $arrOutput = $this->schema->renderOutput($result, $href, $user);
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager($tenantId);
                    $eventId = $revisionObj->newEvent(
                                            'Event - Saving New Udc Value', 
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
                    return ErrorHelper::getNotFoundError("Udc");
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
     * Update value of udc type to database
     * @date 17-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $sysCode
     * @param  string $typeId
     * @param  string $valueId
     * @return updated value of udc type json
     */
    public function update($tenantId,$sysCode,$typeId,$valueId){        
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            if(isset($this->data["recordType"])){ unset($this->data["recordType"]); }
            //Add pre-defined fields to input data
            $this->data["tenant"] = $this->me->getTenantId();
            $this->data["sysCode"] = $this->me->getSysCode();
            $this->data["udcCode"] = $this->me->getTypeId();
            $validateSMS = $this->schema->validate($this->data);            
            if($validateSMS["status"]){
                    $udcObj = $this->me->getUdc($tenantId, $sysCode, $typeId);
                    if($udcObj){
                        $criteria = new CriteriaOption();
                        $criteria->where(UdcValue::FIELD_RECORD_TYPE, $this->me->getRecordType());
                        $criteria->where(UdcValue::FIELD_TENANT, $tenantId);
                        $criteria->where(UdcValue::FIELD_SYS_CODE, $sysCode);
                        $criteria->where(UdcValue::FIELD_UDC, $udcObj->code);
                        $criteria->whereRaw($this->me->getDefaultFilter($valueId),$this->schema->getAllFields(),$user);
                        $this->me->pushCriteria($criteria);
                        $this->me->applyCriteria();
                        $updateObj = $this->me->readOne($this->schema->getAttributes());
                        if($updateObj){
                            $aclObj = new ACL($tenantId, $user, $this->schema);
                            if(!$aclObj->canChange($updateObj)){
                                return ErrorHelper::getPermissionError();
                            }
                            $findObj = $udcObj->values()
                                        ->where(UdcValue::FIELD_RECORD_TYPE, $this->me->getRecordType())
                                        ->where(UdcValue::FIELD_TENANT, $tenantId)
                                        ->where(UdcValue::FIELD_SYS_CODE, $sysCode)
                                        ->where(UdcValue::FIELD_UDC, $udcObj->code)
                                        ->where('code', $this->data["code"])->first();
                            if($updateObj->code != $this->data["code"] && isset($findObj)){
                                //Validate request with unique code of udc value
                                $validator = Validator::make($this->data,[
                                    'code'      =>  'unique:udcs,code'
                                ]);
                                if ($validator->fails()){
                                    return ErrorHelper::getUniqueValidationError($validator->errors()->first('code'));
                                }
                            }
                            //Get udc value object by id for value-relationship
                            $oldObj = $this->me->readOne($this->schema->getAttributes());
                            $href = env('APP_URL') .env('APP_VERSION').'/tenants/' . $tenantId . '/systems/'. $sysCode . '/types/' . $typeId . '/values/'. $oldObj->_id;
                            $oldTargetObj = $this->schema->renderOutput($oldObj, $href, $user);
                            //Render input and update udc value object
                            $saveObj = $this->schema->renderInput($this->data,$updateObj,$user,"U");
                            $outputObj = $this->me->save($saveObj);
                            //Updating value-relationship data
                            $relationshipObj = new RelationshipManager($tenantId);
                            $relationshipObj->update($this->schema->getAllFields(), 
                                                    $this->data, 
                                                    $oldTargetObj, 
                                                    $outputObj, 
                                                    $this->schema->getPluralName(), 
                                                    $user);
                            //Render output and return updated resource in json
                            $href = env('APP_URL') .env('APP_VERSION').'/tenants/' . $tenantId . '/systems/'. $sysCode . '/types/' . $typeId . '/values/'. $outputObj->_id;
                            $result = $this->schema->renderOutput($outputObj,$href,$user);
                            //Saving audit trail and data versioning
                            $revisionObj = new RevisionManager($tenantId);
                            $eventId = $revisionObj->newEvent(
                                                    'Event - Modifying Udc Value', 
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
                            return ErrorHelper::getNotFoundError("Udc Value");
                        }
                    }else{
                        return ErrorHelper::getNotFoundError("Udc");
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
     * Delete value of udc type from database
     * @date 17-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId
     * @param string $sysCode
     * @param string $typeId
     * @param string $valueId
     * @return -
     */
    public function remove($tenantId,$sysCode,$typeId,$valueId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $udcObj = $this->me->getUdc($tenantId, $sysCode, $typeId);
            if($udcObj){
                $criteria = new CriteriaOption();
                $criteria->where(UdcValue::FIELD_RECORD_TYPE, $this->me->getRecordType());
                $criteria->where(UdcValue::FIELD_TENANT, $tenantId);
                $criteria->where(UdcValue::FIELD_SYS_CODE, $sysCode);
                $criteria->where(UdcValue::FIELD_UDC, $udcObj->code);
                $criteria->whereRaw($this->me->getDefaultFilter($valueId), $this->schema->getAllFields(),$user);
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
                        $href = env('APP_URL') .env('APP_VERSION').'/tenants/' . $tenantId . '/systems/'. $sysCode . '/types/' . $typeId . '/values/'. $targetObj->_id;
                        $tObj = $this->schema->renderOutput($targetObj, $href, $user);
                        $relationshipObj->remove($this->schema->getAllFields(), 
                                                    $tObj, 
                                                    $this->schema->getPluralName()
                                                );
                        //Saving audit trail and data versioning
                        $revisionObj = new RevisionManager($tenantId);
                        $eventId = $revisionObj->newEvent(
                                                'Event - Deleting Udc Value', 
                                                $user
                                            );
                        $revisionData[] = $revisionObj->getRevision($eventId, 
                                                $this->schema->getPluralName(), 
                                                $valueId, 
                                                null, 
                                                null, 
                                                null, 
                                                'Delete');
                        $revisionObj->insertData($revisionData, $user);
                        if($this->me->remove()){ return Response()->json(array())->setStatusCode(200); }
                    }else{
                        return ErrorHelper::getInUsedError("Udc Value");
                    }
                }else{
                    return ErrorHelper::getNotFoundError("Udc Value");
                }
            }else{
                return ErrorHelper::getNotFoundError("Udc");
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
     * list all value of udc type from database
     * @date 17-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $sysCode
     * @param  string $typeId
     * @return list of value udc type json
     */
    public function readAll($tenantId, $sysCode, $typeId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $udcObj = $this->me->getUdc($tenantId, $sysCode, $typeId);
            if($udcObj){
                $criteria   =   new CriteriaOption();
                $criteria->where(UdcValue::FIELD_RECORD_TYPE, $this->me->getRecordType()); 
                $criteria->where(UdcValue::FIELD_TENANT, $tenantId);
                $criteria->where(UdcValue::FIELD_SYS_CODE, $sysCode);
                $criteria->where(UdcValue::FIELD_UDC, $udcObj->code);
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
                $ds = $this->me->readAll();
                $dsHref = env("APP_URL").env("APP_VERSION") . "/tenants/" . $tenantId . "/systems/" . $sysCode . "/types/" . $typeId . "/values";
                if(isset($this->data['expand']) && $this->data['expand'] == 'type'){
                    $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user, "type", "TUDCTYPE","", $udcObj);
                }else{
                    $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user);
                }
                return response()->json($resultSet);
            }else{
                return ErrorHelper::getNotFoundError("Udc");
            }
        }catch (\InvalidArgumentException $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
        catch (\Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException $ex){
            return ErrorHelper::getExceptionError($ex->getAwsErrorMessage());
        }
        catch(\Firebase\JWT\ExpiredException $exp){
            return ErrorHelper::getExpiredAccessTokenError();
        }
        catch(\Exception $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
    }
    /**
     * A value of udc type from database
     * @date 17-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $sysCode
     * @param  string $typeId
     * @param  string $valueId
     * @return a value of udc type json
     */
    public function readOne($tenantId,$sysCode,$typeId,$valueId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $udcObj = $this->me->getUdc($tenantId, $sysCode, $typeId);
            if($udcObj){
                $criteria = new CriteriaOption();
                $criteria->where(UdcValue::FIELD_RECORD_TYPE, $this->me->getRecordType());
                $criteria->where(UdcValue::FIELD_TENANT, $tenantId);
                $criteria->where(UdcValue::FIELD_SYS_CODE, $sysCode);
                $criteria->where(UdcValue::FIELD_UDC, $udcObj->code);
                $criteria->whereRaw($this->me->getDefaultFilter($valueId), $this->schema->getAllFields(),$user);
                $this->me->pushCriteria($criteria);
                $this->me->applyCriteria();
                $valueObj = $this->me->readOne();
                if($valueObj){
                    $aclObj = new ACL($tenantId, $user, $this->schema);
                    if(!$aclObj->canRead($valueObj)){
                        return ErrorHelper::getPermissionError();
                    }
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId .'/systems/'. $sysCode . '/types/'. $typeId . "/values/" . $valueObj->_id;
                    $arrValue = $this->schema->renderOutput($valueObj, $href, $user);
                    if(isset($this->data['expand']) && $this->data['expand'] == 'type'){
                        $typeSchema = new SchemaRender("TUDCTYPE");
                        $typeHref = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId .'/systems/'. $sysCode . '/types/'. $typeId;
                        $arrValue["type"] = $typeSchema->renderOutput($udcObj, $typeHref, $user);
                    }
                    return response()->json($arrValue);
                }else{
                    return ErrorHelper::getNotFoundError('Udc Value');
                }
            }else{
                return ErrorHelper::getNotFoundError("Udc");
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