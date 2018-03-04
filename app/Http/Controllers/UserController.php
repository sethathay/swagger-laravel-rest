<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Tenant;
use App\Http\Controllers\User\User;
use App\Http\Controllers\Tenant\Tenant as TN;
use App\Http\Controllers\User\Token;
use App\Http\Controllers\AddressBook\AddressBook as AB;
use App\Http\Controllers\Udc\UdcValueFoundation;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\CognitoAws\CognitoAws;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Jobs\SendInvitationEmail;
use App\Jobs\AcceptInvitationEmail;
use DateTime;
use DateTimeZone;
use DateInterval;
use App\Http\Controllers\BaseLibrary\WeControls\WeDate;

class UserController extends Controller
{
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TUSER";
    private $schemaToken = "TTOKEN";
    private $awsCognito;
    
    //Initailize constructor of udc value controller
    public function __construct(Request $request){
        $this->data = $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me = new User();
        $this->schema = new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Save new user to collection user of database
     * @date 11-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return newly created user json
     */
    public function create($user){
        try{
//            $validateSMS = $this->schema->validate($this->data);
//            if($validateSMS["status"]){
                $obj = $this->schema->renderInput($this->data, $this->me->getModel(), $user, "U");
                $userObj = $this->me->save($obj);
                $href = env('APP_URL') .env('APP_VERSION').'/users/' . $userObj->_id;
                $arrUser = $this->schema->renderOutput($userObj,$href,$user);
                $arrUser['tenants'] = $this->renderTenant($userObj);
                return response()->json($arrUser);
//            }else{
//                return ErrorHelper::getExceptionError($validateSMS["message"]);
//            }
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
     * Display the specified user resource by id.
     * @date 26-Aug-2016
     * @author Seng Sathya <sathya.seng@workevolve.com>
     * @param  int  $userId user id or authentication provider id
     * @return json user information
     */
    public function readOne($userId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            //Get data information of current requested user
            if((isset($user->_id) && $user->_id == $userId)
                || (isset($user->email) && $user->email == $userId)){
                $criteria = new CriteriaOption();
                $criteria->where('_id', $userId);
                $criteria->orWhere('email', $userId);
                $this->me->pushCriteria($criteria);
                $this->me->applyCriteria();
                $userObj = $this->me->readOne();
                if($userObj){
                    $href = env('APP_URL') .env('APP_VERSION').'/users/' . $userObj->_id;
                    $arrUser = $this->schema->renderOutput($userObj,$href,$user);
                    $arrUser['tenants'] = $this->renderTenant($userObj);
                    return response()->json($arrUser);
                }else{
                    return ErrorHelper::getNotFoundError('User');
                }
            }else{
                if(isset($this->data['tenantId']) && !empty($this->data['tenantId'])
                    && isset($this->data['appCode']) && !empty($this->data['appCode'])
                    && isset($this->data['envCode']) && !empty($this->data['envCode'])){
                    $data = $this->validateSender($user, $this->data, 'B');
                    //Error occured in validateSender function
                    if(User::isError($data)){ return $data; }//return error getting from validateSender
                    $criteria = new CriteriaOption();
                    $criteria->where('tenants.id', $this->data['tenantId']);
                    $criteria->where('tenants.apps.app.code', $this->data['appCode']);
                    $criteria->where('tenants.apps.envs.env.code', $this->data['envCode']);
                    $criteria->where('tenants.apps.envs.user_type.code', $data['type']);
                    if($data['type'] == TN::VALUE_EXTERNAL){
                        $criteria->where('tenants.apps.envs.addressbook_id', $this->data['addressBookId']);
                    }
                    $criteria->where('_id',$userId);
                    $this->me->pushCriteria($criteria);
                    $this->me->applyCriteria();
                    $userObj = $this->me->readOne();
                    $href = env('APP_URL') .env('APP_VERSION').'/users/' . $userObj->_id;
                    $arrUser = $this->schema->renderOutput($userObj,$href,$user);
                    $arrUser['tenants'] = $this->renderTenant($userObj);
                    return response()->json($arrUser);
                }else{
                    return ErrorHelper::getRequestBodyError();
                }   
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
     * Function to render tenant info in user info
     * @date 08-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param object $user object of user
     * @return rendered data of tenant
     */
    public function renderTenant($user){
        $indT = 0;
        $tempT = array();
        foreach($user->tenants as $tn){
            $indA = 0;
            $tempA = array();
            foreach($tn->apps as $ap){
                $tenantInfo = Tenant::Where('_id', $tn->id)->first();
                if($tenantInfo->created_by == $user->_id){
                    $ap["role"] = "subscriber";
                }else{
                    $ap["role"] = "normal";
                }
                //Check if tenant of this user subscribe to any project tracking plan
                if($tenantInfo->subscribed('PT')){
                    if($tenantInfo->subscribedToPlan('PT000','PT')){
                        $ap["plan"] = "PT000";
                    }elseif($tenantInfo->subscribedToPlan('PT001','PT')){
                        $ap["plan"] = "PT001";
                    }elseif($tenantInfo->subscribedToPlan('PT002','PT')){
                        $ap["plan"] = "PT002";
                    }elseif($tenantInfo->subscribedToPlan('PT003','PT')){
                        $ap["plan"] = "PT003";
                    }
                }else{
                    $ap["plan"] = "PT000";
                }
                $tempA[$indA] = $ap;
                $indA ++;
            }
            $tn->apps = $tempA;
            $tempT[$indT] = $tn;
            $indT ++;
        }
        return $tempT;
    }
    /**
     * Remove the specified user account in the specified tenant, application and environment
     * @date 26-Dec-2016
     * @author Choomnan Im <chomnan.im@workevolve.com>
     * @param string $id It's user id|user authenticate id
     * @return json empty
     */
    public function removeInvitation($userId){
        try{
            //Validate access_token/inviter account
            $user = $this->awsCognito->validateAccessToken();
            //Validate request body params
            $validator = Validator::make($this->data,[
                'tenantId'      =>  'required',
                'appCode' =>  'required',
                'envCode' =>  'required'  
            ]);
            if ($validator->fails()) { return ErrorHelper::getRequestBodyError(); }
            $data = $this->validateSender($user, $this->data);
            //Error occured in validateSender function
            if(User::isError($data)){ return $data; }//return error getting from validateSender
            $criteria = new CriteriaOption();
            $criteria->where('_id', $userId);
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $selectedUser = $this->me->readOne();
            if($selectedUser){
                if($selectedUser->status['code'] == TN::VALUE_USR_ACTIVE){
                    $this->setTokenExpiration($selectedUser, $this->data);
                    //Delete document of old selected user
                    $updatedData['tenants'] = $this->removeEnvironment($selectedUser, $this->data);
                    $uObj = new User();
                    $uData = $this->schema->renderInput($updatedData, $selectedUser, $user, "U");
                    $uObj->save($uData);
                }elseif($selectedUser->status['code'] == TN::VALUE_USR_PENDING){
                    //Delete document of old selected user
                    $updatedData['tenants'] = $this->removeEnvironment($selectedUser, $this->data);
                    $uObj = new User();
                    $uData = $this->schema->renderInput($updatedData, $selectedUser, $user, "U");
                    $uObj->save($uData);
                }
                return response()->json(array())->setStatusCode(200);
            }else{
                return ErrorHelper::getNotFoundError('User');
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
     * Display a listing of user
     * @date 06-10-2016
     * @author Choomnan Im <chomnan.im@workevolve.com>
     * @return json A listing< of user
     */
    public function readAll(){
        try{
            //Validate access_token
            $userRequest = $this->awsCognito->validateAccessToken();
            //Validation request url params
            $validator = Validator::make($this->data,[
                'tenantId'  =>  'required',
                'appCode'   =>  'required',
                'envCode'   =>  'required'
            ]);
            if ($validator->fails()){ return ErrorHelper::getRequestUrlParamsError(); }
            $data = $this->validateSender($userRequest, $this->data, 'B');
            //Error occured in validateSender function
            if(User::isError($data)){ return $data; }//return error getting from validateSender
            $criteria = new CriteriaOption();
            $criteria->where('tenants.id',$this->data['tenantId']);
            $criteria->where('tenants.apps.app.code',  $this->data['appCode']);
            $criteria->where('tenants.apps.envs.env.code',$this->data['envCode']);
            if($data['type'] == TN::VALUE_EXTERNAL){
                $criteria->where('tenants.apps.envs.user_type.code', TN::VALUE_EXTERNAL);
            }
            if(isset($this->data['status']) && !empty($this->data['status'])){
                $criteria->where('tenants.apps.envs.status.code',$this->data['status']);
            }
            if(isset($this->data['q']) && !empty($this->data['q'])){
                $queryName = explode(' ', $this->data['q']);
                $criteria->orWhere('first_name','LIKE','%' . $this->data['q'] . '%');
                $criteria->orWhere('last_name','LIKE', '%' . $this->data['q'] . '%');
                for($i=0; $i<count($queryName); $i++){
                    $criteria->orWhere('first_name','LIKE','%' . $queryName[$i] . '%');
                    $criteria->orWhere('last_name','LIKE','%' . $queryName[$i] . '%');
                }
                $criteria->orWhere('email','LIKE', '%'. $this->data['q'] . '%');
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
            $ds = $this->me->readAll();
            $dsHref = env("APP_URL").env("APP_VERSION") . "/users";
            $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $userRequest);
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
     * Update the specified user in datastore.
     * @date 26-Aug-2016
     * @author Seng Sathya <sathya.seng@workevolve.com>
     * @param  int  $userId ID of user
     * @return json updated user information
     */
    public function update($userId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                $criteria = new CriteriaOption();
                $criteria->where('_id',$userId);
                $this->me->pushCriteria($criteria);
                $this->me->applyCriteria();
                $userObj = $this->me->readOne();
                if($userObj){
                    //logic of activation and deactivation of user
                    if(isset($this->data['tenantId']) && !empty($this->data['tenantId'])
                        && isset($this->data['appCode']) && !empty($this->data['appCode'])
                        && isset($this->data['envCode']) && !empty($this->data['envCode'])
                        && isset($this->data['envStatus']) && !empty($this->data['envStatus'])){
                        $data = $this->validateSender($user, $this->data);
                        //Error occured in validateSender function
                        if(User::isError($data)){ return $data; }//return error getting from validateSender
                        $tenants = $userObj->tenants()->toArray();
                        $result = $this->getKeys(
                                $tenants, 
                                $this->data['tenantId'], 
                                $this->data['appCode'], 
                                $this->data['envCode'], 
                                $this->data['addressBookId']
                            );
                        $environment = $result['env'];
                        $tenantKey = $result['tenantKey'];
                        $applicationKey = $result['appKey'];
                        $environmentKey = $result['envKey'];
                        $environment['status']  =   UdcValueFoundation::getUdcValue(
                                                                    TN::VALUE_SYS_CODE,
                                                                    TN::TYPE_STATUS,
                                                                    $this->data['envStatus']);
                        $tenants[$tenantKey]['apps'][$applicationKey]['envs'][$environmentKey] = $environment;
                        $this->data['tenants'] = $tenants;
                    }
                    $obj = $this->schema->renderInput($this->data, $userObj, $user, "U");
                    $outputObj = $this->me->save($obj);
                    /*
                    |--------------------------------------------------------------------------
                    |   UPLOADING USER PROFILE PHOTO TO AWS S3
                    |--------------------------------------------------------------------------
                    */
                    $s3 = \Storage::disk('s3');
                    if(isset($this->data['image_action'])){
                        $imageChanged  =  $this->data['image_action'];
                        $oldImage      =  $userObj->image; 
                        switch ($imageChanged) {
                            case 1: // user removed profile image
                                $this->data['image'] = '';
                                $s3->delete(env('S3_BUCKETFOLDER').$oldImage);  
                                break;
                            case 2: // user changed profile image
                                $imagename         = $userId.$this->data['image']->getClientOriginalName();    
                                $profile_pic       = $this->data['image'];
                                $this->data['image'] = $imagename;
                                break;
                            default:  // nothing happended
                                unset($this->data['image']);
                                break;
                        }
                        unset($this->data['image_action']);
                    }
                    if($outputObj && isset($imageChanged)){         
                         if($imageChanged!=3 && isset($this->data['image']) && $this->data['image']!=='') {
                             if(!$s3->exists(env('S3_BUCKETFOLDER').$imagename)){
                                 $s3->delete(env('S3_BUCKETFOLDER').$oldImage);
                                 $s3->put(env('S3_BUCKETFOLDER').$imagename, 
                                          file_get_contents($profile_pic->getPathName()),
                                          'public'
                                         );
                            }
                        }
                    }
                    /*
                    |--------------------------------------------------------------------------
                    |   END - UPLOADING USER PROFILE PHOTO TO AWS S3
                    |--------------------------------------------------------------------------
                    */
                    $href = env('APP_URL') .env('APP_VERSION').'/users/' . $outputObj->_id;
                    $arrUser = $this->schema->renderOutput($outputObj,$href,$user);
                    $arrUser['tenants'] = $this->renderTenant($outputObj);
                    return response()->json($arrUser);
                }else{
                    return ErrorHelper::getNotFoundError('User');
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
     * Store user account / send email to notify user
     * @date 25-Nov-2016
     * @author Chomnan Im <chomnan.im@workevolve.com>
     * @return json User information
     */
    public function sendInvitation(){ 
        try{
            //Validate access token of sender
            $sender = $this->awsCognito->validateAccessToken();
            //Calling validateData function
            $val = $this->validateData($this->data, $sender);
            //Error occure in function validateData
            if(User::isError($val)){ return $val; }
            $tenantObj = $val;
            //Try to get receiver account with requested email address
            $usrObj = new User();
            $usrCriteria = new CriteriaOption();
            $usrCriteria->where('email', $this->data['email']);
            $usrObj->pushCriteria($usrCriteria);
            $usrObj->applyCriteria();
            $receiver = $usrObj->readOne();
            //Receiver is not exists in database or NO ACCOUNT IN AWS COGNITO
            if(empty($receiver)){
                //Create new user account into databases
                $newAccount = $this->prepareNewAccount($tenantObj, $this->data);
                $usrData = $this->schema->renderInput($newAccount, $this->me->getModel(), $sender, "C");
                $this->me->save($usrData);
                //Create ACCOUNT IN AWS COGNITO (METHOD: AdminCreateUser)
                $this->awsCognito->createUser($this->data);
            }
            //Receiver already has account in database
            else{
                //Calling function getTenant to get sub document of tenant to
                //insert into receiver document
                $val = $this->getTenant($receiver, $this->data, $tenantObj);
                //Error occured in getTenant function
                if(User::isError($val)){ return $val; }//return error getting from getTenant
                if($receiver->status['code'] == TN::VALUE_USR_ACTIVE){
                    //Update receiver information of tanant, application and environment
                    $data['tenants'] = $val;
                    $usrData = $this->schema->renderInput($data, $receiver, $sender, "U");
                    $this->me->save($usrData);
                    //Send email invitation
                    $this->sendEmail($sender, $receiver, $tenantObj, $this->data);
                }
                elseif($receiver->status['code'] == TN::VALUE_USR_PENDING){
                    //Update first and last name into database
                    $data['firstName'] = $this->data['firstName'];
                    $data['lastName'] = $this->data['lastName'];
                    $data['tenants'] = $val;
                    $usrData = $this->schema->renderInput($data, $receiver, $sender, "U");
                    $this->me->save($usrData);
                    //Resend email and update user attribute in AWS Cognito
                    //Resend using method AdminCreateUser
                    $this->awsCognito->updateUser($this->data);
                }
            }
            return Response()->json()->setStatusCode(201);
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
     * Send email and update or update user account base user id
     * @date 24-Dec-2016
     * @author Chomnan Im <chomnan.im@workevolve.com>
     * @param string $userId
     * @return json user information
     */
    public function resendInvitation($userId){
        try{
            //Validate access token of sender
            $sender = $this->awsCognito->validateAccessToken();
            //Calling validateData function
            $val = $this->validateData($this->data, $sender);
            //Error occure in function validateData
            if(User::isError($val)){ return $val;}
            $tenantObj = $val;
            //Try to get receiver account with requested user id
            $usrObj = new User();
            $usrCriteria = new CriteriaOption();
            $usrCriteria->where('_id', $userId);
            $usrObj->pushCriteria($usrCriteria);
            $usrObj->applyCriteria();
            $receiver = $usrObj->readOne();
            if(empty($receiver)){ return ErrorHelper::getNotFoundError ('User'); }
            //Status of user account is pending (NEW USER ACCOUNT)
            if($receiver->status['code'] == TN::VALUE_USR_PENDING){
                //Email of receiver is the same as requested email
                if($receiver->email == $this->data['email']){
                    //Update first and last name into database
                    $data['firstName'] = $this->data['firstName'];
                    $data['lastName'] = $this->data['lastName'];
                    $usrData = $this->schema->renderInput($data, $receiver, $sender, "U");
                    $this->me->save($usrData);
                    //Resend email and update user attribute in AWS Cognito
                    //Resend using method AdminCreateUser
                    $this->awsCognito->updateUser($this->data);
                }
                //Email of receiver is different from requested email
                else{
                    //Checking requested new email address
                    $usrObj = new User();
                    $usrCriteria = new CriteriaOption();
                    $usrCriteria->where('email', $this->data['email']);
                    $usrObj->pushCriteria($usrCriteria);
                    $usrObj->applyCriteria();
                    $newReceiver = $usrObj->readOne();
                    //No registered user with this requested email address
                    if(empty($newReceiver)){
                        //Create user document with data (firstName, lastName, email, tenants)
                        //Create new user account into database
                        $newAccount = $this->prepareNewAccount($tenantObj, $this->data);
                        $usrData = $this->schema->renderInput($newAccount, $this->me->getModel(), $sender, "C");
                        $result = $this->me->save($usrData);
                        //Resend email and update user attribute in AWS Cognito
                        //Resend using method AdminCreateUser
                        $this->awsCognito->createUser($this->data);
                        //Delete document of old receiver
                        $updatedData['tenants'] = $this->removeEnvironment($receiver, $this->data);
                        $uData = $this->schema->renderInput($updatedData, $receiver, $sender, "U");
                        $this->me->save($uData);
                    }
                    //Exist registered user with this email
                    else{
                        //Calling function getTenant to get sub document of tenant to
                        //insert into receiver document
                        $val = $this->getTenant($newReceiver, $this->data, $tenantObj);
                        //Error occured in getTenant function
                        if(User::isError($val)){ return $val; }//return error getting from getTenant
                        //Update new receiver information of tanant, application and environment
                        $data['tenants'] = $val;
                        $usrData = $this->schema->renderInput($data, $newReceiver, $sender, "U");
                        $this->me->save($usrData);
                        //Resend email to user with new email using workEvolve Token technique
                        $this->sendEmail($sender, $newReceiver, $tenantObj, $this->data);
                        //Delete document of old receiver
                        $updatedData['tenants'] = $this->removeEnvironment($receiver, $this->data);
                        $uData = $this->schema->renderInput($updatedData, $receiver, $sender, "U");
                        $this->me->save($uData);
                    }
                }
            }
            elseif ($receiver->status['code'] == TN::VALUE_USR_ACTIVE){
                //Email of receiver is the same as requested email
                if($receiver->email == $this->data['email']){
                    //Set expiration of previous invitation
                    $this->setTokenExpiration($receiver, $this->data);
                    //Resend email to invited user using workEvolve Token technique
                    $this->sendEmail($sender, $receiver, $tenantObj, $this->data);
                }else{
                    //Checking requested new email address
                    $usrObj = new User();
                    $usrCriteria = new CriteriaOption();
                    $usrCriteria->where('email', $this->data['email']);
                    $usrObj->pushCriteria($usrCriteria);
                    $usrObj->applyCriteria();
                    $newReceiver = $usrObj->readOne();
                    //No registered user with this requested email address
                    if(empty($newReceiver)){
                        //Create user document with data (firstName, lastName, email, tenants)
                        //Create new user account into database
                        $newAccount = $this->prepareNewAccount($tenantObj, $this->data);
                        $usrData = $this->schema->renderInput($newAccount, $this->me->getModel(), $sender, "C");
                        $result = $this->me->save($usrData);
                        //Create new account in AWS Cognito & Send resend email
                        //Create ACCOUNT IN AWS COGNITO (METHOD: AdminCreateUser)
                        $this->awsCognito->createUser($this->data);
                        //Set expiration of previous invitation
                        $this->setTokenExpiration($receiver, $this->data);
                        //Delete document of old receiver
                        $updatedData['tenants'] = $this->removeEnvironment($receiver, $this->data);
                        $uData = $this->schema->renderInput($updatedData, $receiver, $sender, "U");
                        $this->me->save($uData);
                    }
                    //Exist registered user with this email
                    else{
                        //Add sub document of tenant to new receiver
                        //Calling function getTenant to get sub document of tenant to
                        //insert into receiver document
                        $val = $this->getTenant($newReceiver, $this->data, $tenantObj);
                        //Error occured in getTenant function
                        if(User::isError($val)){ return $val; }//return error getting from getTenant
                        //Update receiver information of tanant, application and environment
                        $data['tenants'] = $val;
                        $usrData = $this->schema->renderInput($data, $newReceiver, $sender, "U");
                        $result = $this->me->save($usrData);
                        //Resend email using workEvolve technique
                        $this->sendEmail($sender, $newReceiver, $tenantObj, $this->data);
                        //Set expiration of previous invitation
                        $this->setTokenExpiration($receiver, $this->data);
                        //Delete document of old receiver
                        $updatedData['tenants'] = $this->removeEnvironment($receiver, $this->data);
                        $uData = $this->schema->renderInput($updatedData, $receiver, $sender, "U");
                        $this->me->save($uData);
                    }
                }
            }
            return Response()->json()->setStatusCode(200);
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
     * Get the specified user account base on token id
     * @date 12-Dec-2016
     * @author Chomnan Im <chomnan.im@workevolve.com>
     * @param string $tokenId
     * @return json User information
     */
    public function getInvitation($tokenId){
        try {
            $result = $this->validateToken($tokenId);
            //If error code exist just return error object
            if(User::isError($result)){ return $result; }
            $environment = $result['env'];
            $organization = $result['organization'];
            $user = $result['users'];
            $token = $result['token'];
            if($environment['status']['code'] == TN::VALUE_USR_ACTIVE){
                return ErrorHelper::getAccountActivatedError('WorkEvolve', $organization->name, $user->email);
            }else if($environment['status']['code'] == TN::VALUE_USR_PENDING){
                $api                =   array();
                $api['id']          =   $user->id;
                $api['firstName']   =   $user->first_name;
                $api['lastName']    =   $user->last_name;
                $api['email']       =   $user->email;
                $weDateObj = new WeDate(null, null);
                $api['expireDate']  =   $weDateObj->getJulianDateTime($token->expire_date, $user);
                $api['organization']=   $organization->name;
                return response()->json($api);
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
     * Update user account base on token id
     * @date 15-Dec-2016
     * @author Chomnan Im <chomnan.im@workevolve.com>
     * @param string $tokenId
     * @return json User information
     */
    public function acceptInvitation($tokenId){ 
        try {
            $result = $this->validateToken($tokenId);
            //If error code exist just return error object
            if(User::isError($result)){ return $result; }
            $environment = $result['env'];
            $tenantKey = $result['tenantKey'];
            $applicationKey = $result['appKey'];
            $environmentKey = $result['envKey'];
            $organization = $result['organization'];
            $user = $result['users'];
            $tenants = $user->tenants()->toArray();
            $token = $result['token'];
            if($environment['status']['code'] == TN::VALUE_USR_ACTIVE){
                return ErrorHelper::getAccountActivatedError('WorkEvolve', $organization->name, $user->email);
            }else if($environment['status']['code'] == TN::VALUE_USR_PENDING){
                //Update status of receiver to "Active" in found environment
                $environment['status']  =   UdcValueFoundation::getUdcValue(
                                                                    TN::VALUE_SYS_CODE,
                                                                    TN::TYPE_STATUS,
                                                                    TN::VALUE_USR_ACTIVE);
                $tenants[$tenantKey]['apps'][$applicationKey]['envs'][$environmentKey] = $environment;
                //Update receiver information of tanant, application and environment
                $data['tenants'] = $tenants;
                $usrData = $this->schema->renderInput($data, $user, $user, "U");
                $this->me->save($usrData);
                //Get sender information based on token info (created_by)
                $cond = new CriteriaOption();
                $uObj = new User();
                $cond->where('_id', $token->created_by);
                $uObj->pushCriteria($cond);
                $uObj->applyCriteria();
                $inviter = $uObj->readOne();
                $reciever   =   array(
                    'firstName' => $inviter->first_name,
                    'lastName' => $inviter->last_name,
                    'email' => $inviter->email
                );
                $subject    =   'Your invitation has been accepted';
                $description=   'Mr/Ms.' . $user->first_name.' '.$user->last_name.' with this email '.$user->email.' has been accepted your invitation to WorkEvolve Cloud Application.';   
                //Sending accept invitation email using queue AWS SQS
                $job    =   (new AcceptInvitationEmail($reciever,$subject,$description))->onConnection('sqs');
                $this->dispatch($job);
                return Response()->json()->setStatusCode(200);
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
     * Preparing data for new user account adding to database
     * @date 16-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param object $tenantObj
     * @param object $data
     * @return array data of new user account
     */
    private function prepareNewAccount($tenantObj, $data){
        //Prepare data of application
        $app = $this->getApplication($data['appCode']);
        $app['envs'][] = $this->getEnvironment(
                                $data['addressBookId'],
                                $data['envCode'],
                                TN::VALUE_USR_PENDING
                            ); 
        //Prepare data of tenant
        $tenant['id']   = $tenantObj->id;
        $tenant['name'] = $tenantObj->name;
        $tenant['apps'][] = $app;   
        $tenants[] = $tenant;
        //prepare data of new user account
        $newAcc = array();
        $newAcc['firstName']    =   $data['firstName'];
        $newAcc['lastName']     =   $data['lastName'];
        $newAcc['email']        =   $data['email'];
        $newAcc['status']       =   TN::VALUE_USR_PENDING;
        $newAcc['contactEmail'] =   array([
                                            'type' => TN::VALUE_CONT_EMAIL,
                                            'value' => $data['email']
                                        ]);
        $newAcc['tenants']      =   $tenants;
        return $newAcc;
    }
    /**
     * Validate token from requested token id
     * @date 12-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $id string token id
     * @return array
     */
    private function validateToken($id){
        $tObj = new Token();
        $tCriteria = new CriteriaOption();
        $tCriteria->where('_id', $id);
        $tObj->pushCriteria($tCriteria);
        $tObj->applyCriteria();
        $token = $tObj->readOne();
        if(empty($token)){ return ErrorHelper::getNotFoundError ('Token'); }
        $criteria = new CriteriaOption();
        $criteria->where('_id', $token->user_id);
        $this->me->pushCriteria($criteria);
        $this->me->applyCriteria();
        $user = $this->me->readOne();
        if(isset($token->addressbook_id) && !empty($token->addressbook_id)){
            $abObj = new AB();
            $abCriteria = new CriteriaOption();
            $abCriteria->where('_id', $token->addressbook_id);
            $abCriteria->where('_tenant', $token->tenant_id);
            $abObj->pushCriteria($abCriteria);
            $abObj->applyCriteria();
            $organization = $abObj->readOne();
        }else{
            $tObj = new TN();
            $tCriteria = new CriteriaOption();
            $tCriteria->where('_id', $token->tenant_id);
            $tObj->pushCriteria($tCriteria);
            $tObj->applyCriteria();
            $organization = $tObj->readOne();
        }
        //User not found
        if(empty($user)){ return ErrorHelper::getAccountRemovedError($organization->name); }
        //Checking expiration of requested token
        $tz = "UTC";
        if(isset($user->time_zone)){ $tz = $user->time_zone["label"]; }
        $dt = new DateTime("now",new DateTimeZone($tz));
        $weDateObj = new WeDate(null, null);
        $now = $weDateObj->setJulianDateTime($dt->format('Y-m-d H:i:s'), $user);
        if($token->expire_date['datetime'] < $now['datetime']){ return ErrorHelper::getExpiredTokenError($organization->name); }
        //Get all tenant of receiver 
        $tenants = $user->tenants()->toArray();
        $result = $this->getKeys(
                    $tenants, 
                    $token->tenant_id, 
                    $token->app['code'],
                    $token->env['code'],
                    $token->addressbook_id
                );
        return array('token' => $token,
                     'organization' => $organization,
                     'env' => $result['env'],
                     'users' => $user,
                     'tenantKey' => $result['tenantKey'],
                     'appKey' => $result['appKey'],
                     'envKey' => $result['envKey']
                );
    }
    /**
     * Get key of tenant, app, env in array tenants of user
     * @date 19-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param array $tenants
     * @param string $tenantId
     * @param string $appCode
     * @param string $envCode
     * @param string $addressBookId
     * @return array
     */
    private function getKeys($tenants, $tenantId, $appCode, $envCode, $addressBookId){
        //Get the specified tenant of receiver based on token info(tenant_id)
        foreach ($tenants as $key => $t) {
            if($t['id'] == $tenantId){
                $tenant     =   $t;
                $tenantKey  =   $key;
                break;
            }  
        }
        //Get the specified application of receiver based on token info(app.code)
        foreach ($tenant['apps'] as $key => $app){
            if($app['app']['code'] == $appCode){
                $application    =   $app;
                $applicationKey =   $key;
                break;
            }
        }
        //Get the specified environment of receiver based on token info(env.code, addressbook_id)
        if(isset($addressBookId) && !empty($addressBookId)){
            foreach ($application['envs'] as $key => $env) {
                if( $env['env']['code'] == $envCode &&
                    $env['user_type']['code'] == TN::VALUE_EXTERNAL && 
                    $env['addressbook_id'] == $addressBookId){
                    $environment    =   $env;
                    $environmentKey =   $key;
                    break;
                }   
            }
        }else{
            foreach ($application['envs'] as $key => $env){
                if($env['env']['code'] == $envCode &&
                    $env['user_type']['code'] == TN::VALUE_EMPLOYEE){
                    $environment    =   $env;
                    $environmentKey =   $key;
                    break;
                }
            }
        }
        return array(
            'tenantKey' => $tenantKey,
            'appKey' => $applicationKey,
            'envKey' => $environmentKey,
            'env' => $environment
        );
    }
    /**
     * WorkEvolve technique of sending email invitation
     * @date 14-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param Object $sender 
     * @param Object $receiver
     * @param Object $tenantObj
     * @param Array $data requested data
     * @return -
     */
    private function sendEmail($sender, $receiver, $tenantObj, $data){
        if(isset($data['emailMessage']) && !empty($data['emailMessage'])){
            //Create token for receiver 
            $token  =   array();
            $token['userId'] = $receiver->id;
            $token['tenantId'] = $tenantObj->id;
            $token['tenantName'] = $tenantObj->name;
            $token['app'] = $data['appCode'];
            $token['env'] = $data['envCode'];
            if(isset($data['addressBookId']) && !empty($data['addressBookId'])) $token['addressBookId'] = $data['addressBookId'];
            $tz = "UTC";
            if(isset($sender->time_zone)){ $tz = $sender->time_zone["label"]; }
            $dt = new DateTime("now",new DateTimeZone($tz));
            $dt->add(new DateInterval('P14D')); //Expiration in 14days
            $token['expireDate'] = $dt->format('Y-m-d H:i:s');
            $tokenSchema = new SchemaRender($this->schemaToken);
            $tokenObj = new Token();
            $data = $tokenSchema->renderInput($token, $tokenObj->getModel(), $sender, "C");
            $newToken = $tokenObj->save($data);
            //Prepare data for send email to receiver
            if(!empty($newToken)){
                $from = array(
                            'firstName' => $sender->first_name,
                            'lastName' => $sender->last_name,
                            'email' => $sender->email
                        );
                $to = array(
                            'firstName' => $receiver->first_name,
                            'lastName' => $receiver->last_name,
                            'email' => $receiver->email
                        );
                $subject = 'Invitation to join ';
                $link = env('SSO_URL').'/c?token=' . $newToken->id;
                if(isset($data['addressBookId']) && !empty($data['addressBookId'])){
                    $abObj = new AB();
                    $abCriteria = new CriteriaOption();
                    $abCriteria->where('_id', $data['addressBookId']);
                    $abCriteria->where('_tenant', $data['tenantId']);
                    $abObj->pushCriteria($abCriteria);
                    $abObj->applyCriteria();
                    $organization = $abObj->readOne();
                    $subject = $subject . $organization->name;  
                }else{
                    $subject = $subject . $tenantObj->name;   
                }
                //Send invitation email using AWS SQS
                $job = (new SendInvitationEmail($from, $to, $subject, $link, $data['emailMessage']))->onConnection('sqs');
                $this->dispatch($job);
            }
        }
    }
    /**
     * Set expiration of token for specific user, tenant, app, env, external
     * @date 17-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param object $receiver
     * @param object $data
     * @return -
     */
    private function setTokenExpiration($receiver, $data){
        $criteria = new CriteriaOption();
        $tokenSchema = new SchemaRender($this->schemaToken);
        $tokenObj = new Token();
        $criteria->where('user_id', $receiver->id);
        $criteria->where('tenant_id', $data['tenantId']);
        $criteria->where('app.code', $data['appCode']);
        $criteria->where('env.code', $data['envCode']);
        if(isset($data['addressBookId']) && !empty($data['addressBookId'])){
            $criteria->where('addressbook_id', $data['addressBookId']);
        }
        $criteria->orderBy('expire_date.datetime desc');
        $tokenObj->pushCriteria($criteria);
        $tokenObj->applyCriteria();
        $updateObj = $tokenObj->readOne();
        if(empty($updateObj)){ return ErrorHelper::getNotFoundError ('Token'); }
        $tz = "UTC";
        if(isset($receiver->time_zone)){ $tz = $receiver->time_zone["label"]; }
        $dt = new DateTime("now",new DateTimeZone($tz));
        $data['expireDate'] = $dt->format('Y-m-d H:i:s');
        $updateData = $tokenSchema->renderInput($data, $updateObj, $receiver, "U");
        $tokenObj->save($updateData);
    }
    /**
     * Get info of tenant, app, env to insert to user docs
     * @date 16-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param object $receiver
     * @param object $data
     * @param object $tenantObj
     * @return object sub docs to insert to user docs
     */
    private function getTenant($receiver, $data, $tenantObj){
        //Prepare update the exist receiver account
        $tenants = $receiver->tenants()->toArray();
        //Check sender and receiver tenant (SAME OR DIFFERENT)
        foreach ($tenants as $key => $t) {
            if($t['id'] == $data['tenantId']){
                $tenantKey =   $key;
                $tenant    =   $t;
                break;
            }
        }
        //Tenant of sender is different from tenant of receiver
        if(empty($tenant)){
            $pApp = $this->getApplication($data['appCode']);
            $pApp['envs'][] = $this->getEnvironment($data['addressBookId'], $data['envCode'], TN::VALUE_USR_PENDING);
            $pTenant['id'] = $tenantObj->id;
            $pTenant['name'] = $tenantObj->name;
            $pTenant['apps'][] = $pApp;
            array_push($tenants,$pTenant);
        }
        //Tenant of sender is the same as tenant of receiver
        else{
            //Check sender and receiver application (SAME OR DIFFERENT)
            foreach ($tenant['apps'] as $key => $app){
                if($app['app']['code'] == $data['appCode']){
                    $applicationKey =   $key;
                    $application    =   $app;
                    break;
                }
            }
            //Application of sender is different from application of receiver
            if(empty($application)){
                $pApp = $this->getApplication($data['appCode']);
                $pApp['envs'][] = $this->getEnvironment($data['addressBookId'], $data['envCode'], TN::VALUE_USR_PENDING);
                array_push($tenants[$tenantKey]['apps'], $pApp);
            }
            //Application of sender is the same as application of receiver
            else{
                //Check sender and receiver environment (SAME OR DIFFERENT)
                foreach ($application['envs'] as $key => $env){
                    if($env['env']['code'] == $data['envCode']){
                        $environments[]  =   $env;
                    }
                }
                //Environment of sender is different from environment of receiver
                if(empty($environments)){
                    $pEnv = $this->getEnvironment($data['addressBookId'], $data['envCode'], TN::VALUE_USR_PENDING);
                }
                //Environment of sender is the same as environment of receiver
                else{
                    //Get user type of receiver and list of external (addressBookId) in the specified environment
                    $userType = User::getUserType($environments);
                    //Check existing of external (Invite for external user)
                    if(isset($data['addressBookId']) && !empty($data['addressBookId'])){
                        //Receiver is external user
                        if($userType['type'] == TN::VALUE_EXTERNAL){
                            //Receiver's external (addressBookId) is different from requested external (DIFFERENT)
                            if(!in_array($data['addressBookId'], $userType['listAddressBookId'])){
                                $pEnv = $this->getEnvironment($data['addressBookId'], $data['envCode'], TN::VALUE_USR_PENDING);
                            }
                        }
                    }else{
                        return ErrorHelper::getEmailExistError();
                    }
                }
                array_push($tenants[$tenantKey]['apps'][$applicationKey]['envs'],$pEnv);
            } 
        }
        return $tenants;
    }
    /**
     * Get application array for invitation process
     * @date 11-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $application UDC application code
     * @return application array
     */
    private function getApplication($application){
        //Prepare data of application for embedded
        $appData = array();
        $appData['app'] = UdcValueFoundation::getUdcValue(
                            TN::VALUE_SYS_CODE,
                            TN::TYPE_APP,
                            $application
                        );
        return $appData;
    }
    /**
     * Get environment array for invitation process
     * @date 11-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string  $addressBookId external id for reference to address book
     * @param  string  $environment UDC environment code
     * @param  string  $status UDC status of user
     * @return environment array
     */
    private function getEnvironment($addressBookId, $environment, $status){
        //Prepare data of environment for embedded
        $envData = array();
        $envData['env'] = UdcValueFoundation::getUdcValue(
                            TN::VALUE_SYS_CODE,
                            TN::TYPE_ENV,
                            $environment
                        );
        $envData['status'] = UdcValueFoundation::getUdcValue(
                            TN::VALUE_SYS_CODE, 
                            TN::TYPE_STATUS, 
                            $status
                        );
        //Check existing of external
        if(isset($addressBookId) && !empty($addressBookId)){
            $envData['user_type'] = UdcValueFoundation::getUdcValue(
                                        TN::VALUE_SYS_CODE, 
                                        TN::TYPE_USR, 
                                        TN::VALUE_EXTERNAL
                                    );
            $envData['addressbook_id'] = $addressBookId;
        }else{
            $envData['user_type'] = UdcValueFoundation::getUdcValue(
                                        TN::VALUE_SYS_CODE, 
                                        TN::TYPE_USR, 
                                        TN::VALUE_EMPLOYEE
                                    );
        }
        return $envData;
    }
    /**
     * Remove environment of specific tenant of user account
     * @date 16-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param object $receiver
     * @param object $data
     * @return object environment of specific tenant
     */
    private function removeEnvironment($receiver, $data, $option = "R"){
        //get all tenant of receiver account 
        $tenants = $receiver->tenants()->toArray();
        //get tenant of receiver account
        foreach ($tenants as $key => $t){
            if($t['id'] == $data['tenantId']){
                $tenantKey =   $key;
                $tenant    =   $t;
                break;
            }
        }
        //Get application of invitee account
        foreach ($tenant['apps'] as $key => $app){
            if($app['app']['code'] == $data['appCode']){
                $applicationKey =   $key;
                $application    =   $app;
                break;
            }
        }
        //Get environment of invitee account base on inviter
        if(isset($data['addressBookId']) && !empty($data['addressBookId'])){
            foreach ($application['envs'] as $key => $env){
                if($env['env']['code'] == $data['envCode'] && $env['status']['code'] == TN::VALUE_USR_PENDING 
                    && $env['user_type']['code'] == TN::VALUE_EXTERNAL && $env['addressbook_id'] == $data['addressBookId']){
                    $environmentKey =   $key;
                    $environment    =   $env;
                }
            }   
        }else{
            foreach ($application['envs'] as $key => $env){
                if($env['env']['code'] == $data['envCode'] && $env['status']['code'] == TN::VALUE_USR_PENDING 
                    && $env['user_type']['code'] == TN::VALUE_EMPLOYEE){
                    $environmentKey =   $key;
                    $environment    =   $env;
                }
            } 
        }
        //Remove existing invitee environment 
        unset($tenants[$tenantKey]['apps'][$applicationKey]['envs'][$environmentKey]);
        if($option == "R"){
            //User is not exist in other environment under the app
            if(count($tenants[$tenantKey]['apps'][$applicationKey]['envs']) == 0){
                //Remove existing invitee application
                unset($tenants[$tenantKey]['apps'][$applicationKey]);
                //User is not exist in other app under the tenant
                if(count($tenants[$tenantKey]['apps']) == 0){
                    //Remove existing invitee tenant
                    unset($tenants[$tenantKey]);
                }
            }
        }
        return $tenants;
    }
    /**
     * Validate sender with the requested tenant, application, and environment
     * @date 17-Jan-2017
     * @author Chomnan Im <chomnan.im@workevolve.com>
     * @param object $sender information of sender account
     * @param object $data requested data
     * @return -
     */
    private function validateSender($sender, $data, $option = 'A'){
        //validate sender is authorized with requested tenant
        $tenant = $sender->tenants()->where('id', $data['tenantId'])->first();
        if(empty($tenant)){ return ErrorHelper::getForbiddenError(); }
        //validate sender is authorized with requested app
        $application = null;
        foreach ($tenant->apps as $key => $app) {
            if($app['app']['code'] == $data['appCode']){
                $application = $app;
                break;
            }
        }
        if(empty($application)){ return ErrorHelper::getForbiddenError(); }
        //validate sender is authorized with requested app
        $environments = array();
        foreach ($application['envs'] as $key => $env) {
            if($env['env']['code'] == $data['envCode']){
                $environments[] = $env;
            }
        }
        if(empty($environments)){ return ErrorHelper::getForbiddenError(); }
        //Get user type of sender and list of external in the specified environment
        $userType = User::getUserType($environments);
        //if sender is external system need to check required body param external (addressBookId)
        //because if sender is external can invite only external user type
        if($option == 'A'){
            if($userType['type'] == TN::VALUE_EXTERNAL){
                //validate existing of external (addressBookId)
                if(!isset($data['addressBookId']) || empty($data['addressBookId'])){ return ErrorHelper::getRequestBodyError(); }
                //Get external really belong to tenant of sender
                $abObj = new AB();
                $abCriteria = new CriteriaOption();
                $abCriteria->where('_id', $data['addressBookId']);
                $abCriteria->where('_tenant', $data['tenantId']);
                $abObj->pushCriteria($abCriteria);
                $abObj->applyCriteria();
                if($abObj->count() == 0){ return ErrorHelper::getForbiddenError(); }
                //external (addressBookId) must belong to sender's external
                //because sender can invite external user to only their external
                if(!in_array($data['addressBookId'], $userType['listAddressBookId'])){ return ErrorHelper::getForbiddenError(); }
            }elseif($userType['type'] == TN::VALUE_EMPLOYEE){
                //validate existing of external (addressBookId)
                if(isset($data['addressBookId']) && !empty($data['addressBookId'])){
                    //Get external really belong to tenant of sender
                    $abObj = new AB();
                    $abCriteria = new CriteriaOption();
                    $abCriteria->where('_id', $data['addressBookId']);
                    $abCriteria->where('_tenant', $data['tenantId']);
                    $abObj->pushCriteria($abCriteria);
                    $abObj->applyCriteria();
                    if($abObj->count() == 0){ return ErrorHelper::getForbiddenError(); }
                }
            }
        }
        return $userType;
    }
    /**
     * Validate permission of sender for specific tenant, app, env, and external
     * @date 14-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param Illuminate\Http\Request $request
     * @param Object $sender
     * @return Object of Valid Tenant
     */
    private function validateData($request, $sender){
        //Validate request body params
        $validator = Validator::make($request,[
            'tenantId'  =>  'required',
            'appCode'   =>  'required',
            'envCode'   =>  'required',
            'firstName' =>  'required|max:35',
            'lastName'  =>  'required|max:35',
            'email'     =>  'required|email',   
        ]);
        if ($validator->fails()){ return ErrorHelper::getRequestBodyError(); }
        //Sender and receiver has the same email address
        if($sender->email == $request['email']){ return ErrorHelper::getEmailExistError(); }
        //Check existing of tenant
        $tObj = new TN();
        $criteria = new CriteriaOption();
        $criteria->where('_id', $request['tenantId']);
        $tObj->pushCriteria($criteria);
        $tObj->applyCriteria();
        $tenantObj = $tObj->readOne();
        if(empty($tenantObj)){ return ErrorHelper::getNotFoundError('Tenant'); }
        //validate sender for requested tenant, app, and env of receiver
        $data = $this->validateSender($sender, $request);
        //Error occured in validateSender function
        if(User::isError($data)){ return $data; }//return error getting from validateSender
        return $tenantObj;
    }
}
