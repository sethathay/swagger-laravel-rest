<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\PostLibrary\Feedback;
use App\Http\Controllers\Project\Project;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\AddressBook\Customer;

class FeedbackController extends Controller
{
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TPOST";
    private $awsCognito;
    
    //Initailize constructor of object library controller
    public function __construct(Request $request){
        $this->data     =   $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me       =   new Feedback();
        $this->schema   =   new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Use to store feedback of each project
     * @date 26-Aug-2016
     * @author Seng Sathya <seng.sathya@workevolve.com>
     * @param string $tenantId Id of subscription tenant
     * @param string $projectId Id of project
     * @return json $response/$feedback
     */
    public function create($tenantId, $projectId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Validate tenant of user request
                $tenantInUser =  $user->tenants()->where('tenant_id',$tenantId)->first(); 
                if(empty($tenantInUser)){ return ErrorHelper::getForbiddenError();}
                
                //$customerList= Customer::getCurrentCustomerIdOfUser($tenantInUser);
                //if(count($customerList) <= 0){ return ErrorHelper::getForbiddenError(); }
                
                $pObj = new Project($tenantId);
                //Getting project by tenantId and projectId
                $criteria = new CriteriaOption();
                $criteria->where('_tenant',$pObj->getTenantId());
                $criteria->where('_id',$projectId);
                $pObj->pushCriteria($criteria);
                $pObj->applyCriteria();
                //Read all but return only first found result
                $project = $pObj->readOne();
                if($project){
                    $this->data["type"] = $this->me->getType();
                    $obj = $this->schema->renderInput($this->data, $this->me->getModel(),$user,"C");
                    //Massage data of user's info
                    $userData = array();
                    $userData["_id"] = $user->id;
                    $userData["name"] = $user->first_name . ' ' . $user->last_name;
                    if(isset($user->image) && $user->image != ""){
                        $userData["image"] = $user->image;
                    }
                    //Massage data of file attachments
                    if(isset($this->data["files"])){
                        $fileData = array_values($this->data["files"]);
                        // Move files from temporary to main folder of each tenant
                        $s3 =   \Storage::disk('s3');
                        foreach ($this->data["files"] as $key=>$file){
                             $oldfilepathAndName = env('S3_BUCKETFOLDER_TEMP_ATTACHMENTS').$file['path'];
                             $newfilepathAndName = env('S3_BUCKETFOLDER_ATTACHMENTS').$file['path'];
                             $s3->move($oldfilepathAndName, $newfilepathAndName, 'public');
                        }
                        $obj->files = $fileData;
                    }
                    //Massage data of like/unlike
                    $obj->user = $userData;
                    $obj->_tenant = $tenantId;
                    $obj->_project = $projectId;
                    $outputObj = $this->me->save($obj);
                    //Save back to project with id of feedback
                    $project->push('feedbacks',(string) $outputObj->_id);
                    $project->save();
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/projects/'. $projectId . '/feedbacks/' . $outputObj->_id;
                    $output = $this->schema->renderOutput($outputObj,$href,$user);
                    return response()->json($output);
                }else{
                    return ErrorHelper::getNotFoundError('Project or User');
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
     * Use to update feedback of each project
     * @date 02-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId Id of subscription tenant
     * @param string $projectId Id of project
     * @param string $feedbackId Id of feedback
     * @return json $response/$feedback
     */
    public function update($tenantId, $projectId, $feedbackId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Validate tenant of user request
                $tenantInUser =  $user->tenants()->where('tenant_id',$tenantId)->first(); 
                if(empty($tenantInUser)){ return ErrorHelper::getForbiddenError();}
                
                //$customerList= Customer::getCurrentCustomerIdOfUser($tenantInUser);
                //if(count($customerList) <= 0){ return ErrorHelper::getForbiddenError(); }
                
                $pObj = new Project($tenantId);
                //Getting project by tenantId and projectId
                $criteria = new CriteriaOption();
                $criteria->where('_tenant',$pObj->getTenantId());
                $criteria->where('_id',$projectId);
                $pObj->pushCriteria($criteria);
                $pObj->applyCriteria();
                //Read all but return only first found result
                $project = $pObj->readOne();
                if($project){
                    //Get update feedback object
                    $feedbackObj = new Feedback();
                    $ct = new CriteriaOption();
                    $ct->where('_id',$feedbackId);
                    $feedbackObj->pushCriteria($ct);
                    $feedbackObj->applyCriteria();
                    $updatedObj = $feedbackObj->readOne($this->schema->getAttributes());
                    
                    $this->data["type"] = $this->me->getType();
                    //unset like text before render
                    $likeUnlikeText = "";
                    if(isset($this->data["like"])){
                        $likeUnlikeText = $this->data["like"];
                        unset($this->data["like"]);
                    }
                    $obj = $this->schema->renderInput($this->data, $updatedObj,$user,"U");
                    //Massage data of user's info
                    $userData = array();
                    $userData["_id"] = $user->id;
                    $userData["name"] = $user->first_name . ' ' . $user->last_name;
                    if(isset($user->image) && $user->image != ""){
                        $userData["image"] = $user->image;
                    }
                    //Massage data of file attachments
                    if(isset($this->data["files"])){
                        $fileData = array_values($this->data["files"]);
                        // Move files from temporary to main folder of each tenant
                        $s3 =   \Storage::disk('s3');
                        foreach ($this->data["files"] as $key=>$file){
                             $oldfilepathAndName = env('S3_BUCKETFOLDER_TEMP_ATTACHMENTS').$file['path'];
                             $newfilepathAndName = env('S3_BUCKETFOLDER_ATTACHMENTS').$file['path'];
                             $s3->move($oldfilepathAndName, $newfilepathAndName, 'public');
                        }
                        $obj->files = $fileData;
                    }
                    //Massage data of like/unlike
                    if($likeUnlikeText == "like"){
                        $likeData = array();
                        $likeData['_id'] = $user->id;
                        $likeData['name'] = $user->first_name . ' ' . $user->last_name;
                        if(isset($user->image) && $user->image != ""){
                            $likeData['image'] = $user->image;
                        }
                        $obj->push('like',$likeData);
                    }elseif($likeUnlikeText == "unlike"){
                        $obj->pull('like',array('_id' => (string) $user->id));
                    }
                    $obj->user = $userData;
                    $obj->_tenant = $tenantId;
                    $obj->_project = $projectId;
                    $outputObj = $this->me->save($obj);
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/projects/'. $projectId . '/feedbacks/' . $outputObj->_id;
                    $output = $this->schema->renderOutput($outputObj,$href,$user);
                    return response()->json($output);
                }else{
                    return ErrorHelper::getNotFoundError('Project or User');
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
     * Get clicking information for expanding feedback level 2
     * @date 01-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId Id of subscription tenant
     * @param string $projectId Id of project
     * @param string $resultSet resultSet to add more info to
     * @return json $response/$feedback
     */
    private function createClickingInfoLevel2($tenantId, $projectId, $resultSet, $user){
        //Special data arrange before output
        $i = 0;
        foreach($resultSet['items'] as $fbObj){
            if(!empty($fbObj)){
                $j = 0;
                foreach($fbObj["replies"]['items'] as $replyL1){
                    if(!empty($replyL1)){
                        //prepare data to insert for link click showing level 2
                        $url = explode("/", $replyL1['href']);
                        //Url index count-3 is feedbackId index count-1 is reply level 1 id
                        $lvTwo = $this->_readAllReplyofReply($tenantId, $projectId, $url[count($url)-3], $url[count($url)-1], $user)->getData();
                        if($lvTwo->size > 0){
                            $resultSet['items'][$i]["replies"]['items'][$j]["replies"]['href'] = $lvTwo->href;
                            $resultSet['items'][$i]["replies"]['items'][$j]["replies"]['size'] = $lvTwo->size;
                            $resultSet['items'][$i]["replies"]['items'][$j]["replies"]['lastUser'] = $lvTwo->items[count($lvTwo->items)-1]->user->name;
                            if(isset($lvTwo->items[count($lvTwo->items)-1]->user->image)){
                                $resultSet['items'][$i]["replies"]['items'][$j]["replies"]['image'] = $lvTwo->items[count($lvTwo->items)-1]->user->image;
                            }
                        }
                        $j++;
                    }
                }
            }
            $i++;
        }
        return $resultSet;
    }
    /**
     * Get feedbacks of each project
     * @date 31-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId Id of subscription tenant
     * @param string $projectId Id of project
     * @return json $response/$feedback
     */
    public function readAll($tenantId, $projectId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            //Validate tenant of user request
            $tenantInUser =  $user->tenants()->where('tenant_id',$tenantId)->first(); 
            if(empty($tenantInUser)){ return ErrorHelper::getForbiddenError();}
            
            //Validate weCloudApp of user request
            $weCloudApp = ErrorHelper::validateUserApp($tenantInUser);
            if(empty($weCloudApp)){ return ErrorHelper::getForbiddenError();}
            
            $pObj = new Project($tenantId);
            //Getting project by tenantId and projectId
            $criteria = new CriteriaOption();
            $criteria->where('_tenant',$pObj->getTenantId());
            $criteria->where('_id',$projectId);
            $pObj->pushCriteria($criteria);
            $pObj->applyCriteria();
            //Read all but return only first found result
            $project = $pObj->readOne();
            if($project){
                $customerList= Customer::getCurrentCustomerIdOfUser($tenantInUser);
                $criteria   =   new CriteriaOption();
                //Return back empty array when there is no feedback
                if(!isset($project->feedbacks)){
                    $dsHref = env("APP_URL").env("APP_VERSION")."/tenants/" . $tenantId ."/projects/" . $projectId . "/feedbacks";
                    return $this->schema->renderOutputDS($this->schema, [], $dsHref, 0, $offset, $limit, $user);
                }
                //Output feedback between tenant and customer
                if(count($customerList)<= 0){
                    $criteria->where('type.code',$this->me->getType());
                    $criteria->whereIn('_id', $project->feedbacks);
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
                    
                    $dsHref = env("APP_URL").env("APP_VERSION") . "/tenants/" . $tenantId . "/projects/" . $projectId . "/feedbacks";
                    if(isset($this->data['expand']) && $this->data['expand'] == 'replies'){
                        $ds = $this->me->readAll();
                        $rs = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, count($project->feedbacks), $offset, $limit, $user, "replies", "TPOST", "replies");
                        $resultSet = $this->createClickingInfoLevel2($tenantId, $projectId, $rs, $user);
                    }else{
                        $ds = $this->me->readAll($this->schema->getAttributes());
                        $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, count($project->feedbacks), $offset, $limit, $user);
                    }
                    return response()->json($resultSet);
                }else{
                    $criteria->where('type.code',$this->me->getType());
                    $criteria->where('created_by', $user->_id);
                    $criteria->whereIn('_id', $project->feedbacks);
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
                    
                    $dsHref = env("APP_URL").env("APP_VERSION") . "/tenants/" . $tenantId . "/projects/" . $projectId . "/feedbacks";
                    if(isset($this->data['expand']) && $this->data['expand'] == 'replies'){
                        $ds = $this->me->readAll();
                        $rs = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user, "replies", "TPOST", "replies");
                        $resultSet = $this->createClickingInfoLevel2($tenantId, $projectId, $rs, $user);
                    }else{
                        $ds = $this->me->readAll($this->schema->getAttributes());
                        $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user);
                    }
                    return response()->json($resultSet);
                }
            }else{
                return ErrorHelper::getNotFoundError('Project');
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
     * Get one feedback of each project
     * @date 31-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId Id of subscription tenant
     * @param string $projectId Id of project
     * @param string $feedbackId Id of feedback in project
     * @return json $response/$feedback
     */
    public function readOne($tenantId, $projectId, $feedbackId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            //Validate tenant of user request
            $tenantInUser =  $user->tenants()->where('tenant_id',$tenantId)->first(); 
            if(empty($tenantInUser)){ return ErrorHelper::getForbiddenError();}
            
            //Validate weCloudApp of user request
            $weCloudApp = ErrorHelper::validateUserApp($tenantInUser);
            if(empty($weCloudApp)){ return ErrorHelper::getForbiddenError();}
            
            $pObj = new Project($tenantId);
            //Getting project by tenantId and projectId
            $criteria = new CriteriaOption();
            $criteria->where('_tenant',$pObj->getTenantId());
            $criteria->where('_id',$projectId);
            $pObj->pushCriteria($criteria);
            $pObj->applyCriteria();
            //Read all but return only first found result
            $project = $pObj->readOne();
            if(!$project){ return ErrorHelper::getNotFoundError('Project'); }
            
            $ct = new CriteriaOption();
            $ct->where('_id',$feedbackId);
            $this->me->pushCriteria($ct);
            $this->me->applyCriteria();
            $feedbackObj = $this->me->readOne($this->schema->getAttributes());
            if($feedbackObj){
                $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/projects/'. $projectId . '/feedbacks/' . $feedbackId;
                $outputObj = $this->schema->renderOutput($feedbackObj,$href,$user);
                return response()->json($outputObj);
            }else{
                return ErrorHelper::getNotFoundError('Feedback');
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
     * Use to store reply of feedback of each project
     * @date 31-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId Id of subscription tenant
     * @param string $projectId Id of project
     * @param string $feedbackId Id of feedback in project
     * @return json $response/$feedback
     */
    public function createReply($tenantId, $projectId, $feedbackId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Validate tenant of user request
                $tenantInUser =  $user->tenants()->where('tenant_id',$tenantId)->first(); 
                if(empty($tenantInUser)){ return ErrorHelper::getForbiddenError();}

                //Validate weCloudApp of user request
                $weCloudApp = ErrorHelper::validateUserApp($tenantInUser);
                if(empty($weCloudApp)){ return ErrorHelper::getForbiddenError();}

                $pObj = new Project($tenantId);
                //Getting project by tenantId and projectId
                $criteria = new CriteriaOption();
                $criteria->where('_tenant',$pObj->getTenantId());
                $criteria->where('_id',$projectId);
                $pObj->pushCriteria($criteria);
                $pObj->applyCriteria();
                //Read all but return only first found result
                $project = $pObj->readOne();
                if(!$project){ return ErrorHelper::getNotFoundError('Project'); }

                $obj = new Feedback();
                $ct = new CriteriaOption();
                $ct->where('_id',$feedbackId);
                $obj->pushCriteria($ct);
                $obj->applyCriteria();
                $feedbackObj = $obj->readOne();
                if($feedbackObj){
                    $this->data["type"] = $this->me->getType();
                    $obj = $this->schema->renderInput($this->data, $this->me->getModel(),$user,"C");
                    //Massage data of user's info
                    $userData = array();
                    $userData["_id"] = $user->id;
                    $userData["name"] = $user->first_name . ' ' . $user->last_name;
                    if(isset($user->image) && $user->image != ""){
                        $userData["image"] = $user->image;
                    }
                    //Massage data of file attachments
                    if(isset($this->data["files"])){
                        $fileData = array_values($this->data["files"]);
                        // Move files from temporary to main folder of each tenant
                        $s3 =   \Storage::disk('s3');
                        foreach ($this->data["files"] as $key=>$file){
                             $oldfilepathAndName = env('S3_BUCKETFOLDER_TEMP_ATTACHMENTS').$file['path'];
                             $newfilepathAndName = env('S3_BUCKETFOLDER_ATTACHMENTS').$file['path'];
                             $s3->move($oldfilepathAndName, $newfilepathAndName, 'public');
                        }
                        $obj->files = $fileData;
                    }
                    $obj->user = $userData;
                    $obj->_tenant = $tenantId;
                    $obj->_project = $projectId;
                    $outputObj = $feedbackObj->replies()->save($obj);
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/projects/'. $projectId . '/feedbacks/' . $feedbackId . '/replies/' . $outputObj->_id;
                    $output = $this->schema->renderOutput($outputObj,$href,$user);
                    return response()->json($output);
                }else{
                    return ErrorHelper::getNotFoundError('Feedback');
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
     * Use to update feedback of each project
     * @date 02-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId Id of subscription tenant
     * @param string $projectId Id of project
     * @param string $feedbackId Id of feedback
     * @param string $replyLevel1Id Id reply of feedback
     * @return json $response/$feedback
     */
    public function updateReply($tenantId, $projectId, $feedbackId, $replyLevel1Id){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Validate tenant of user request
                $tenantInUser =  $user->tenants()->where('tenant_id',$tenantId)->first(); 
                if(empty($tenantInUser)){ return ErrorHelper::getForbiddenError();}
                
                //$customerList= Customer::getCurrentCustomerIdOfUser($tenantInUser);
                //if(count($customerList) <= 0){ return ErrorHelper::getForbiddenError(); }
                
                $pObj = new Project($tenantId);
                //Getting project by tenantId and projectId
                $criteria = new CriteriaOption();
                $criteria->where('_tenant',$pObj->getTenantId());
                $criteria->where('_id',$projectId);
                $pObj->pushCriteria($criteria);
                $pObj->applyCriteria();
                //Read all but return only first found result
                $project = $pObj->readOne();
                if($project){
                    //Get update feedback object
                    $feedbackObj = new Feedback();
                    $ct = new CriteriaOption();
                    $ct->where('_id',$feedbackId);
                    $feedbackObj->pushCriteria($ct);
                    $feedbackObj->applyCriteria();
                    $fdObj = $feedbackObj->readOne();
                    if($fdObj){
                        $updatedObj = $fdObj->replies()->find($replyLevel1Id);

                        $this->data["type"] = $this->me->getType();
                        //unset like text before render
                        $likeUnlikeText = "";
                        if(isset($this->data["like"])){
                            $likeUnlikeText = $this->data["like"];
                            unset($this->data["like"]);
                        }
                        $obj = $this->schema->renderInput($this->data, $updatedObj,$user,"U");
                        //Massage data of user's info
                        $userData = array();
                        $userData["_id"] = $user->id;
                        $userData["name"] = $user->first_name . ' ' . $user->last_name;
                        if(isset($user->image) && $user->image != ""){
                            $userData["image"] = $user->image;
                        }
                        //Massage data of file attachments
                        if(isset($this->data["files"])){
                            $fileData = array_values($this->data["files"]);
                            // Move files from temporary to main folder of each tenant
                            $s3 =   \Storage::disk('s3');
                            foreach ($this->data["files"] as $key=>$file){
                                 $oldfilepathAndName = env('S3_BUCKETFOLDER_TEMP_ATTACHMENTS').$file['path'];
                                 $newfilepathAndName = env('S3_BUCKETFOLDER_ATTACHMENTS').$file['path'];
                                 $s3->move($oldfilepathAndName, $newfilepathAndName, 'public');
                            }
                            $obj->files = $fileData;
                        }
                        //Massage data of like/unlike
                        if($likeUnlikeText == "like"){
                            $likeData = array();
                            $likeData['_id'] = $user->id;
                            $likeData['name'] = $user->first_name . ' ' . $user->last_name;
                            if(isset($user->image) && $user->image != ""){
                                $likeData['image'] = $user->image;
                            }
                            //Need to get key in order to push like data of sub document
                            //Need to use main document object to push data
                            $key = $fdObj->replies()->where('_id',$replyLevel1Id)->keys()->first();
                            $fdObj->push('replies.'. $key .'.like',$likeData);
                        }elseif($likeUnlikeText == "unlike"){
                            //Need to get key in order to push like data of sub document
                            //Need to use main document object to push data
                            $key = $fdObj->replies()->where('_id',$replyLevel1Id)->keys()->first();
                            $fdObj->pull('replies.'. $key . '.like',array('_id' => (string) $user->id));
                        }
                        $obj->user = $userData;
                        $obj->_tenant = $tenantId;
                        $obj->_project = $projectId;
                        $outputObj = $fdObj->replies()->save($obj);
                        $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/projects/'. $projectId . '/feedbacks/' . $feedbackId . '/replies/' . $outputObj->_id;
                        $output = $this->schema->renderOutput($outputObj,$href,$user);
                        return response()->json($output);
                    }else{
                        return ErrorHelper::getNotFoundError('Feedback');
                    }
                }else{
                    return ErrorHelper::getNotFoundError('Project or User');
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
     * Use to update reply of reply of feedback of each project
     * @date 03-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId Id of subscription tenant
     * @param string $projectId Id of project
     * @param string $feedbackId Id of feedback
     * @param string $replyLevel1Id Id reply of feedback
     * @param string $replyLevel2Id Id reply of reply of feedback
     * @return json $response/$feedback
     */
    public function updateReplyofReply($tenantId, $projectId, $feedbackId, $replyLevel1Id, $replyLevel2Id){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Validate tenant of user request
                $tenantInUser =  $user->tenants()->where('tenant_id',$tenantId)->first(); 
                if(empty($tenantInUser)){ return ErrorHelper::getForbiddenError();}
                
                //$customerList= Customer::getCurrentCustomerIdOfUser($tenantInUser);
                //if(count($customerList) <= 0){ return ErrorHelper::getForbiddenError(); }
                
                $pObj = new Project($tenantId);
                //Getting project by tenantId and projectId
                $criteria = new CriteriaOption();
                $criteria->where('_tenant',$pObj->getTenantId());
                $criteria->where('_id',$projectId);
                $pObj->pushCriteria($criteria);
                $pObj->applyCriteria();
                //Read all but return only first found result
                $project = $pObj->readOne();
                if($project){
                    //Get update feedback object
                    $feedbackObj = new Feedback();
                    $ct = new CriteriaOption();
                    $ct->where('_id',$feedbackId);
                    $feedbackObj->pushCriteria($ct);
                    $feedbackObj->applyCriteria();
                    $fdObj = $feedbackObj->readOne();
                    if($fdObj){
                        $replyObj = $fdObj->replies()->find($replyLevel1Id);
                        $key1 = $fdObj->replies()->where('_id',$replyLevel1Id)->keys()->first();
                        if($replyObj){
                            $updatedObj = $replyObj->replies()->find($replyLevel2Id);
                            
                            $this->data["type"] = $this->me->getType();
                            //unset like text before render
                            $likeUnlikeText = "";
                            if(isset($this->data["like"])){
                                $likeUnlikeText = $this->data["like"];
                                unset($this->data["like"]);
                            }
                            $obj = $this->schema->renderInput($this->data, $updatedObj,$user,"U");
                            //Massage data of user's info
                            $userData = array();
                            $userData["_id"] = $user->id;
                            $userData["name"] = $user->first_name . ' ' . $user->last_name;
                            if(isset($user->image) && $user->image != ""){
                                $userData["image"] = $user->image;
                            }
                            //Massage data of file attachments
                            if(isset($this->data["files"])){
                                $fileData = array_values($this->data["files"]);
                                // Move files from temporary to main folder of each tenant
                                $s3 =   \Storage::disk('s3');
                                foreach ($this->data["files"] as $key=>$file){
                                     $oldfilepathAndName = env('S3_BUCKETFOLDER_TEMP_ATTACHMENTS').$file['path'];
                                     $newfilepathAndName = env('S3_BUCKETFOLDER_ATTACHMENTS').$file['path'];
                                     $s3->move($oldfilepathAndName, $newfilepathAndName, 'public');
                                }
                                $obj->files = $fileData;
                            }
                            //Massage data of like/unlike
                            if($likeUnlikeText == "like"){
                                $likeData = array();
                                $likeData['_id'] = $user->id;
                                $likeData['name'] = $user->first_name . ' ' . $user->last_name;
                                if(isset($user->image) && $user->image != ""){
                                    $likeData['image'] = $user->image;
                                }
                                //Need to get key in order to push like data of sub document
                                //Need to use main document object to push data
                                $key2 = $replyObj->replies()->where('_id',$replyLevel2Id)->keys()->first();
                                $fdObj->push('replies.'. $key1 .'.replies.' . $key2 . '.like',$likeData);
                            }elseif($likeUnlikeText == "unlike"){
                                //Need to get key in order to push like data of sub document
                                //Need to use main document object to push data
                                $key2 = $replyObj->replies()->where('_id',$replyLevel2Id)->keys()->first();
                                $fdObj->pull('replies.'. $key1 .'.replies.' . $key2 . '.like',array('_id' => (string) $user->id));
                            }
                            $obj->user = $userData;
                            $obj->_tenant = $tenantId;
                            $obj->_project = $projectId;
                            $outputObj = $replyObj->replies()->save($obj);
                            $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/projects/'. $projectId . '/feedbacks/' . $feedbackId . '/replies/' . $replyLevel1Id . '/replies/' . $outputObj->_id;
                            $output = $this->schema->renderOutput($outputObj,$href,$user);
                            return response()->json($output);
                        }else{
                            return ErrorHelper::getNotFoundError('Reply');
                        }
                    }else{
                        return ErrorHelper::getNotFoundError('Feedback');
                    }
                }else{
                    return ErrorHelper::getNotFoundError('Project or User');
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
     * Get one of reply level 1 of feedback of each project
     * @date 31-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId Id of subscription tenant
     * @param string $projectId Id of project
     * @param string $feedbackId Id of feedback in project
     * @param string $replyLevel1Id Id of reply level 1
     * @return json $response/$feedback
     */
    public function readOneReply($tenantId, $projectId, $feedbackId, $replyLevel1Id){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            //Validate tenant of user request
            $tenantInUser =  $user->tenants()->where('tenant_id',$tenantId)->first(); 
            if(empty($tenantInUser)){ return ErrorHelper::getForbiddenError();}
            
            //Validate weCloudApp of user request
            $weCloudApp = ErrorHelper::validateUserApp($tenantInUser);
            if(empty($weCloudApp)){ return ErrorHelper::getForbiddenError();}
            
            $pObj = new Project($tenantId);
            //Getting project by tenantId and projectId
            $criteria = new CriteriaOption();
            $criteria->where('_tenant',$pObj->getTenantId());
            $criteria->where('_id',$projectId);
            $pObj->pushCriteria($criteria);
            $pObj->applyCriteria();
            //Read all but return only first found result
            $project = $pObj->readOne();
            if(!$project){ return ErrorHelper::getNotFoundError('Project'); }
            
            $obj = new Feedback();
            $ct = new CriteriaOption();
            $ct->where('_id',$feedbackId);
            $obj->pushCriteria($ct);
            $obj->applyCriteria();
            $feedbackObj = $obj->readOne();
            if($feedbackObj){
                $replyObj = $feedbackObj->replies()->find($replyLevel1Id);
                if($replyObj){
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/projects/'. $projectId . '/feedbacks/' . $feedbackId . '/replies/' . $replyObj->_id;
                    $result = $this->schema->renderOutput($replyObj, $href, $user);
                    return response()->json($result);
                }else{
                    return ErrorHelper::getNotFoundError('Reply');
                }
            }else{
                return ErrorHelper::getNotFoundError('Feedback');
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
     * Get all replies of feedbacks of each project
     * @date 01-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId Id of subscription tenant
     * @param string $projectId Id of project
     * @param string $feedbackId Id of feedback
     * @return json $response/$feedback
     */
    public function readAllReply($tenantId, $projectId, $feedbackId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            //Validate tenant of user request
            $tenantInUser =  $user->tenants()->where('tenant_id',$tenantId)->first(); 
            if(empty($tenantInUser)){ return ErrorHelper::getForbiddenError();}
            
            //Validate weCloudApp of user request
            $weCloudApp = ErrorHelper::validateUserApp($tenantInUser);
            if(empty($weCloudApp)){ return ErrorHelper::getForbiddenError();}
            
            $pObj = new Project($tenantId);
            //Getting project by tenantId and projectId
            $criteria = new CriteriaOption();
            $criteria->where('_tenant',$pObj->getTenantId());
            $criteria->where('_id',$projectId);
            $pObj->pushCriteria($criteria);
            $pObj->applyCriteria();
            //Read all but return only first found result
            $project = $pObj->readOne();
            if($project){
                $criteria   =   new CriteriaOption();
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
                $obj = new Feedback();
                $ct = new CriteriaOption();
                $ct->where('_id',$feedbackId);
                $obj->pushCriteria($ct);
                $obj->applyCriteria();
                $feedbackObj = $obj->readOne();
                if($feedbackObj){
                    $ds =   array_slice($feedbackObj->replies->all(),$offset,$limit);
                    $dsHref = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/projects/'. $projectId . '/feedbacks/' . $feedbackId . '/replies';
                    $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, count($feedbackObj->replies), $offset, $limit, $user);
                    return response()->json($resultSet);
                }else{
                    return ErrorHelper::getNotFoundError('Feedback');
                }
            }else{ 
                return ErrorHelper::getNotFoundError('Project');
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
     * Use to store reply of feedback of each project
     * @date 31-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId Id of subscription tenant
     * @param string $projectId Id of project
     * @param string $feedbackId Id of feedback in project
     * @param string $replyLevel1Id Id of reply level 1
     * @return json $response/$feedback
     */
    public function createReplyofReply($tenantId, $projectId, $feedbackId, $replyLevel1Id){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Validate tenant of user request
                $tenantInUser =  $user->tenants()->where('tenant_id',$tenantId)->first(); 
                if(empty($tenantInUser)){ return ErrorHelper::getForbiddenError();}

                //Validate weCloudApp of user request
                $weCloudApp = ErrorHelper::validateUserApp($tenantInUser);
                if(empty($weCloudApp)){ return ErrorHelper::getForbiddenError();}

                $pObj = new Project($tenantId);
                //Getting project by tenantId and projectId
                $criteria = new CriteriaOption();
                $criteria->where('_tenant',$pObj->getTenantId());
                $criteria->where('_id',$projectId);
                $pObj->pushCriteria($criteria);
                $pObj->applyCriteria();
                //Read all but return only first found result
                $project = $pObj->readOne();
                if(!$project){ return ErrorHelper::getNotFoundError('Project'); }

                $obj = new Feedback();
                $ct = new CriteriaOption();
                $ct->where('_id',$feedbackId);
                $obj->pushCriteria($ct);
                $obj->applyCriteria();
                $feedbackObj = $obj->readOne();
                if($feedbackObj){
                    $replyObj = $feedbackObj->replies()->find($replyLevel1Id);
                    if($replyObj){
                        $this->data["type"] = $this->me->getType();
                        $obj = $this->schema->renderInput($this->data, $this->me->getModel(),$user,"C");
                        //Massage data of user's info
                        $userData = array();
                        $userData["_id"] = $user->id;
                        $userData["name"] = $user->first_name . ' ' . $user->last_name;
                        if(isset($user->image) && $user->image != ""){
                            $userData["image"] = $user->image;
                        }
                        //Massage data of file attachments
                        if(isset($this->data["files"])){
                            $fileData = array_values($this->data["files"]);
                            // Move files from temporary to main folder of each tenant
                            $s3 =   \Storage::disk('s3');
                            foreach ($this->data["files"] as $key=>$file){
                                 $oldfilepathAndName = env('S3_BUCKETFOLDER_TEMP_ATTACHMENTS').$file['path'];
                                 $newfilepathAndName = env('S3_BUCKETFOLDER_ATTACHMENTS').$file['path'];
                                 $s3->move($oldfilepathAndName, $newfilepathAndName, 'public');
                            }
                            $obj->files = $fileData;
                        }
                        $obj->user = $userData;
                        $obj->_tenant = $tenantId;
                        $obj->_project = $projectId;
                        $outputObj = $replyObj->replies()->save($obj);
                        $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/projects/'. $projectId . '/feedbacks/' . $feedbackId . '/replies/' . $replyLevel1Id . '/replies/' . $outputObj->_id;
                        $output = $this->schema->renderOutput($outputObj,$href,$user);
                        return response()->json($output);
                    }else{
                        return ErrorHelper::getNotFoundError('Reply');
                    }
                }else{
                    return ErrorHelper::getNotFoundError('Feedback');
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
     * Private function to get all replies of replies of feedbacks of each project
     * @date 01-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId Id of subscription tenant
     * @param string $projectId Id of project
     * @param string $feedbackId Id of feedback
     * @param string $replyLevel1Id Id of reply level 1
     * @param object $user authenticated user object
     * @return json $response/$feedback
     */
    private function _readAllReplyofReply($tenantId, $projectId, $feedbackId, $replyLevel1Id, $user){
        //Validate tenant of user request
        $tenantInUser =  $user->tenants()->where('tenant_id',$tenantId)->first(); 
        if(empty($tenantInUser)){ return ErrorHelper::getForbiddenError();}

        //Validate weCloudApp of user request
        $weCloudApp = ErrorHelper::validateUserApp($tenantInUser);
        if(empty($weCloudApp)){ return ErrorHelper::getForbiddenError();}

        $pObj = new Project($tenantId);
        //Getting project by tenantId and projectId
        $criteria = new CriteriaOption();
        $criteria->where('_tenant',$pObj->getTenantId());
        $criteria->where('_id',$projectId);
        $pObj->pushCriteria($criteria);
        $pObj->applyCriteria();
        //Read all but return only first found result
        $project = $pObj->readOne();
        if($project){
            $criteria   =   new CriteriaOption();
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
            $obj = new Feedback();
            $ct = new CriteriaOption();
            $ct->where('_id',$feedbackId);
            $obj->pushCriteria($ct);
            $obj->applyCriteria();
            $feedbackObj = $obj->readOne();
            if($feedbackObj){
                $replyObj = $feedbackObj->replies()->find($replyLevel1Id);
                if($replyObj){
                    $ds = array_slice($replyObj->replies->all(),$offset,$limit);
                    $dsHref = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/projects/'. $projectId . '/feedbacks/' . $feedbackId . '/replies/' . $replyLevel1Id . '/replies';
                    $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, count($replyObj->replies), $offset, $limit, $user);
                    return response()->json($resultSet);
                }else{
                    return ErrorHelper::getNotFoundError('Reply');
                }
            }else{
                return ErrorHelper::getNotFoundError('Feedback');
            }
        }else{ 
            return ErrorHelper::getNotFoundError('Project');
        }
    }
    /**
     * Get all replies of replies of feedbacks of each project
     * @date 01-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId Id of subscription tenant
     * @param string $projectId Id of project
     * @param string $feedbackId Id of feedback
     * @param string $replyLevel1Id Id of reply level 1
     * @return json $response/$feedback
     */
    public function readAllReplyofReply($tenantId, $projectId, $feedbackId, $replyLevel1Id){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            return $this->_readAllReplyofReply($tenantId, $projectId, $feedbackId, $replyLevel1Id, $user);
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
