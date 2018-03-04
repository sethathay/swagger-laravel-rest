<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostLibrary\Post;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\File\FileManager;
use App\Http\Controllers\Relationship\RelationshipManager;

class PostController extends Controller
{
    private $me;
    private $data;
    private $schema;
    private $schemaReply;
    private $schemaTable = "TPOST";
    private $schemaTableReply = "TREPLY";
    private $awsCognito;
    
    //Initailize constructor of object library controller
    public function __construct(Request $request){
        $this->data = $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me = new Post(Route::current()->parameter('tenantId'),
                             Route::current()->parameter('resource'),
                             Route::current()->parameter('resourceId'));
        $this->schema = new SchemaRender($this->schemaTable);
        $this->schemaReply = new SchemaRender($this->schemaTableReply);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Saving post of provided resource (ex: tasks, projects)
     * @date 25-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId
     * @param string $resource 
     * @param string $resourceId
     * @return json created post
     */
    public function create($tenantId, $resource, $resourceId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            //Add pre-defined fields to input data
            $this->data["tenant"] = $this->me->getTenantId();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                $dataObj = $this->me->getResource();
                if($dataObj){
                    //Save Post Data (with resource id)
                    $this->data["type"] = $this->me->getType();
                    $this->data["resource"] = $resource;
                    $this->data["resourceId"] = $resourceId;
                    $obj = $this->schema->renderInput($this->data, $this->me->getModel(),$user,"C");
                    //Embedded user info to post data
                    $obj->user = $this->getEmbeddedUser($user);
                    $outputObj = $this->me->save($obj);
                    //Saving file attachments to AWS S3
                    if(isset($this->data["files"])){
                        $fileManager = new FileManager($tenantId);
                        $outputObj->files = $fileManager->save($this->data["files"], 'posts', $outputObj->_id, $user);
                        $this->me->save($outputObj);
                    }
                    //Update back resource reference (with post Id)
                    $dataObj->push('posts',(string) $outputObj->_id);
                    $dataObj->save();
                    //Saving value-relationship data
                    $relationshipObj = new RelationshipManager($tenantId);
                    $relationshipObj->save($this->schema->getAllFields(), 
                                            $this->data, 
                                            $outputObj, 
                                            $this->schema->getPluralName(), 
                                            $user);
                    //Relationship of resource with task
                    $relData[] = $relationshipObj->getRelationship(
                                            $this->schema->getPluralName(), 
                                            $outputObj->_id,
                                            Post::FIELD_RESOURCE_ID, 
                                            $resource, 
                                            $resourceId,
                                            "_id");
                    $relationshipObj->insertData($relData, $user);
                    //Render output and return created resource in json
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/'. $resource . '/' . $resourceId . '/posts/' . $outputObj->_id;
                    $output = $this->schema->renderOutput($outputObj, $href, $user);
                    return response()->json($output);
                }else{
                    return ErrorHelper::getNotFoundError($resource);
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
     * Saving reply of post of provided resource (ex: tasks, projects)
     * @date 25-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId
     * @param string $resource
     * @param string $resourceId
     * @param string $postId
     * @return json created reply of post
     */
    public function createReply($tenantId, $resource, $resourceId, $postId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            $validateSMS = $this->schemaReply->validate($this->data);
            if($validateSMS["status"]){
                $dataObj = $this->me->getResource();
                if($dataObj){
                    $postObj = $this->me->getPost($postId);
                    if($postObj){
                        //Save Reply Data (with resource id)
                        $obj = $this->schemaReply->renderInput($this->data, $this->me->getModel(),$user,"C");
                        //Saving file attachments to AWS S3
                        if(isset($this->data["files"])){
                            $obj->files = $this->saveAttachment($this->data["files"]);
                        }
                        //Embedded user info to post data
                        $obj->user = $this->getEmbeddedUser($user);
                        $outputObj = $postObj->replies()->save($obj);
                        $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/'. $resource . '/' . $resourceId . '/posts/' . $postId . '/replies/' . $outputObj->_id;
                        $output = $this->schemaReply->renderOutput($outputObj,$href,$user);
                        return response()->json($output);
                    }else{
                        return ErrorHelper::getNotFoundError('Post');
                    }
                }else{
                    return ErrorHelper::getNotFoundError($resource);
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
     * Saving reply of reply of post of provided resource (ex: tasks, projects)
     * @date 25-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId
     * @param string $resource
     * @param string $resourceId
     * @param string $postId
     * @param string $replyId
     * @return json created reply of reply post
     */
    public function createReplyofReply($tenantId, $resource, $resourceId, $postId, $replyId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            $validateSMS = $this->schemaReply->validate($this->data);
            if($validateSMS["status"]){
                $dataObj = $this->me->getResource();
                if($dataObj){
                    $postObj = $this->me->getPost($postId);
                    if($postObj){
                        $replyObj = $postObj->replies()->find($replyId);
                        if($replyObj){
                            //Save Reply of Reply Data (with resource id)
                            $obj = $this->schemaReply->renderInput($this->data, $this->me->getModel(),$user,"C");
                            //Saving file attachments to AWS S3
                            if(isset($this->data["files"])){
                                $obj->files = $this->saveAttachment($this->data["files"]);
                            }
                            //Embedded user info to post data
                            $obj->user = $this->getEmbeddedUser($user);
                            $outputObj = $replyObj->replies()->save($obj);
                            $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/'. $resource . '/' . $resourceId . '/posts/' . $postId . '/replies/' . $replyId . '/replies/' . $outputObj->_id;
                            $output = $this->schemaReply->renderOutput($outputObj,$href,$user);
                            return response()->json($output);
                        }else{
                            return ErrorHelper::getNotFoundError('Reply');
                        }
                    }else{
                        return ErrorHelper::getNotFoundError('Post');
                    }
                }else{
                    return ErrorHelper::getNotFoundError($resource);
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
     * @param string $tenantId
     * @param string $resource
     * @param string $resourceId
     * @param string $resultSet
     * @param object $user
     * @return json $response/$feedback
     */
    private function createClickingInfoLevel2($tenantId, $resource, $resourceId, $resultSet, $user){
        //Special data arrange before output
        $i = 0;
        foreach($resultSet['items'] as $pObj){
            if(!empty($pObj)){
                $j = 0;
                foreach($pObj["replies"]['items'] as $replyL1){
                    if(!empty($replyL1)){
                        //prepare data to insert for link click showing level 2
                        $url = explode("/", $replyL1['href']);
                        //Url index count-3 is feedbackId index count-1 is reply level 1 id
                        $lvTwo = $this->_readAllReplyofReply($tenantId, $resource, $resourceId, $url[count($url)-3], $url[count($url)-1], $user)->getData();
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
     * Save attachment file to AWS S3
     * @date 25-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param array $files attachment files user attach in post
     * @return string
     */
    private function saveAttachment($files){
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
    /**
     * Getting embedded user info to put in post document
     * @date 25-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param object $user
     * @return array
     */
    private function getEmbeddedUser($user){
        $userData = array();
        $userData["_id"] = $user->id;
        $userData["name"] = $user->first_name . ' ' . $user->last_name;
        if(isset($user->image) && $user->image != ""){
            $userData["image"] = $user->image;
        }
        return $userData;
    }
    /**
    * Provide all posts and can expand reply level1 by parameter expand in 
    * specific by task id and project id.
    * @date 03-Aug-2017
    * @author Setha Thay <setha.thay@workevolve.com>
    * @param string $tenantId
    * @param string $resource
    * @param string $resourceId
    * @return json data from all posts by an task in a project
    */
    public function readAll($tenantId, $resource, $resourceId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $dataObj = $this->me->getResource();
            if($dataObj){
                $criteria = new CriteriaOption();
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
                //Return back empty array when there is no posts
                if(!isset($dataObj->posts)){
                    $dsHref = env("APP_URL").env("APP_VERSION")."/tenants/" . $tenantId ."/" . $resource . "/" . $resource . "/posts";
                    return $this->schema->renderOutputDS($this->schema, [], $dsHref, 0, $offset, $limit, $user);
                }
                $criteria->where(Post::FIELD_TENANT, $this->me->getTenantId());
                $criteria->where('type.code',$this->me->getType());
                $criteria->where(Post::FIELD_RESOURCE, $resource);
                $criteria->where(Post::FIELD_RESOURCE_ID, $resourceId);
                $criteria->whereIn('_id', $dataObj->posts);
                $criteria->orderBy('updated_at.datetime');
                $this->me->pushCriteria($criteria);
                $this->me->applyCriteria();
                $dsHref = env("APP_URL").env("APP_VERSION")."/tenants/" . $tenantId ."/" . $resource . "/" . $resourceId . "/posts";
                if(isset($this->data['expand']) && $this->data['expand'] == 'replies'){
                    $ds = $this->me->readAll();
                    $rs = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, count($dataObj->posts), $offset, $limit, $user, "replies", "TREPLY", "replies");
                    $resultSet = $this->createClickingInfoLevel2($tenantId, $resource, $resourceId, $rs, $user);
                }else{
                    $ds = $this->me->readAll($this->schema->getAttributes());
                    $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, count($dataObj->posts), $offset, $limit, $user);
                }
                return response()->json($resultSet);
            }else{
                return ErrorHelper::getNotFoundError($resource);
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
     * @param string $tenantId
     * @param string $resource
     * @param string $resourceId
     * @param string $postId
     * @param string $replyLevel1Id
     * @param object $user
     * @return json $response/$feedback
     */
    private function _readAllReplyofReply($tenantId, $resource, $resourceId, $postId, $replyLevel1Id, $user){
        $dataObj = $this->me->getResource();
        if($dataObj){
            $criteria = new CriteriaOption();
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
            $postObj = $this->me->getPost($postId);
            if($postObj){
                $replyObj = $postObj->replies()->find($replyLevel1Id);
                if($replyObj){
                    $ds = array_slice($replyObj->replies->all(),$offset,$limit);
                    $dsHref = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/'. $resource . '/' . $resourceId . '/posts/' . $postId . '/replies/' . $replyLevel1Id . '/replies';
                    $resultSet = $this->schemaReply->renderOutputDS($this->schemaReply, $ds, $dsHref, count($replyObj->replies), $offset, $limit, $user);
                    return response()->json($resultSet);
                }else{
                    return ErrorHelper::getNotFoundError('Reply');
                }
            }else{
                return ErrorHelper::getNotFoundError('Post');
            }
        }else{
            return ErrorHelper::getNotFoundError($resource);
        }
    }
    /**
    * This function use for retrieve a listing of reply level 2 in the specified reply level 1.
    * @date 03-Aug-2017
    * @author Setha Thay <setha.thay@workevolve.com>
    * @param string $tenantId
    * @param string $resource
    * @param string $resourceId
    * @param string $postId
    * @param string $replyId
    * @return json A listing of reply level 2.
    */
    public function readAllReplyofReply($tenantId, $resource, $resourceId, $postId, $replyId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            return $this->_readAllReplyofReply($tenantId, $resource, $resourceId, $postId, $replyId, $user);
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
     * Use to update post of a resource
     * @date 03-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId
     * @param string $resource
     * @param string $resourceId
     * @param string $postId
     * @return json $response/$post
     */
    public function update($tenantId, $resource, $resourceId, $postId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            if(isset($this->data["type"])){ unset($this->data["type"]); }
            //Add pre-defined fields to input data
            $this->data["tenant"] = $this->me->getTenantId();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                $dataObj = $this->me->getResource();
                if($dataObj){
                    $updatedObj = $this->me->getPost($postId);
                    if($updateObj){
                        //Get task object by id for value-relationship
                        $oldObj = $this->me->getPost($postId);
                        $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/'. $resource . '/' . $resourceId . '/posts/' . $oldObj->_id;
                        $oldTargetObj = $this->schema->renderOutput($oldObj, $href, $user);
                        //unset like text before render
                        $likeUnlikeText = "";
                        if(isset($this->data["like"])){
                            $likeUnlikeText = $this->data["like"];
                            unset($this->data["like"]);
                        }
                        $obj = $this->schema->renderInput($this->data, $updatedObj,$user,"U");
                        //Saving file attachments to AWS S3
                        if(isset($this->data["files"])){
                            $obj->files = $this->saveAttachment($this->data["files"]);
                        }
                        //Embedded user info to post data
                        $obj->user = $this->getEmbeddedUser($user);
                        if($likeUnlikeText == "like"){
                            $obj->push('like',$this->getEmbeddedUser($user));
                        }elseif($likeUnlikeText == "unlike"){
                            $obj->pull('like',array('_id' => (string) $user->id));
                        }
                        $outputObj = $this->me->save($obj);
                        //Updating value-relationship data
                        $relationshipObj = new RelationshipManager($tenantId);
                        $relationshipObj->update($this->schema->getAllFields(), 
                                                $this->data, 
                                                $oldTargetObj, 
                                                $outputObj, 
                                                $this->schema->getPluralName(), 
                                                $user);
                        //Render output and return updated resource in json
                        $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/'. $resource . '/' . $resourceId . '/posts/' . $outputObj->_id;
                        $output = $this->schema->renderOutput($outputObj,$href,$user);
                        return response()->json($output);
                    }else{
                        return ErrorHelper::getNotFoundError("Post");
                    }
                }else{
                    return ErrorHelper::getNotFoundError($resource);
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
     * Use to update reply of post in task of each project
     * @date 04-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId
     * @param string $resource
     * @param string $resourceId
     * @param string $postId
     * @param string $replyId
     * @return json $response/$post
     */
    public function updateReply($tenantId, $resource, $resourceId, $postId, $replyId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            $validateSMS = $this->schemaReply->validate($this->data);
            if($validateSMS["status"]){
                $dataObj = $this->me->getResource();
                if($dataObj){
                    $pObj = $this->me->getPost($postId);
                    if($pObj){
                        $updatedObj = $pObj->replies()->find($replyId);
                        if($updatedObj){
                            //unset like text before render
                            $likeUnlikeText = "";
                            if(isset($this->data["like"])){
                                $likeUnlikeText = $this->data["like"];
                                unset($this->data["like"]);
                            }
                            $obj = $this->schemaReply->renderInput($this->data, $updatedObj,$user,"U");
                            //Saving file attachments to AWS S3
                            if(isset($this->data["files"])){
                                $obj->files = $this->saveAttachment($this->data["files"]);
                            }
                            //Embedded user info to post data
                            $obj->user = $this->getEmbeddedUser($user);
                            if($likeUnlikeText == "like"){
                                //Need to get key in order to push like data of sub document
                                //Need to use main document object to push data
                                $key = $pObj->replies()->where('_id', $replyId)->keys()->first();
                                $pObj->push('replies.'. $key .'.like', $this->getEmbeddedUser($user));
                            }elseif($likeUnlikeText == "unlike"){
                                //Need to get key in order to push like data of sub document
                                //Need to use main document object to push data
                                $key = $pObj->replies()->where('_id', $replyId)->keys()->first();
                                $pObj->pull('replies.'. $key . '.like',array('_id' => (string) $user->id));
                            }
                            $outputObj = $pObj->replies()->save($obj);
                            $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/'. $resource . '/' . $resourceId . '/posts/' . $postId . '/replies/' . $outputObj->_id;
                            $output = $this->schemaReply->renderOutput($outputObj,$href,$user);
                            return response()->json($output);
                        }else{
                            return ErrorHelper::getNotFoundError('Reply');
                        }
                    }else{
                        return ErrorHelper::getNotFoundError('Post');
                    }
                }else{
                    return ErrorHelper::getNotFoundError($resource);
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
     * Use to update reply of reply of post in task of each project
     * @date 04-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId
     * @param string $resource
     * @param string $resourceId
     * @param string $postId
     * @param string $replyId
     * @param string $replyLevel2Id
     * @return json $response/$post
     */
    public function updateReplyofReply($tenantId, $resource, $resourceId, $postId, $replyId, $replyLevel2Id){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            $validateSMS = $this->schemaReply->validate($this->data);
            if($validateSMS["status"]){
                $dataObj = $this->me->getResource();
                if($dataObj){
                    $pObj = $this->me->getPost($postId);
                    if($pObj){
                        $replyObj = $pObj->replies()->find($replyId);
                        if($replyObj){
                            $key1 = $pObj->replies()->where('_id', $replyId)->keys()->first();
                            $updatedObj = $replyObj->replies()->find($replyLevel2Id);
                            if($updatedObj){
                                //unset like text before render
                                $likeUnlikeText = "";
                                if(isset($this->data["like"])){
                                    $likeUnlikeText = $this->data["like"];
                                    unset($this->data["like"]);
                                }
                                $obj = $this->schemaReply->renderInput($this->data, $updatedObj,$user,"U");
                                //Saving file attachments to AWS S3
                                if(isset($this->data["files"])){
                                    $obj->files = $this->saveAttachment($this->data["files"]);
                                }
                                //Embedded user info to post data
                                $obj->user = $this->getEmbeddedUser($user);
                                if($likeUnlikeText == "like"){
                                    //Need to get key in order to push like data of sub document
                                    //Need to use main document object to push data
                                    $key2 = $replyObj->replies()->where('_id',$replyLevel2Id)->keys()->first();
                                    $pObj->push('replies.'. $key1 .'.replies.' . $key2 . '.like', $this->getEmbeddedUser($user));
                                }elseif($likeUnlikeText == "unlike"){
                                    //Need to get key in order to push like data of sub document
                                    //Need to use main document object to push data
                                    $key2 = $replyObj->replies()->where('_id',$replyLevel2Id)->keys()->first();
                                    $pObj->pull('replies.'. $key1 .'.replies.' . $key2 . '.like',array('_id' => (string) $user->id));
                                }
                                $outputObj = $replyObj->replies()->save($obj);
                                $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/'. $resource . '/' . $resourceId . '/posts/' . $postId . '/replies/' . $replyId . '/replies/' . $outputObj->_id;
                                $output = $this->schemaReply->renderOutput($outputObj, $href, $user);
                                return response()->json($output);
                            }else{
                                return ErrorHelper::getNotFoundError('Reply');
                            }
                        }else{
                            return ErrorHelper::getNotFoundError('Reply');
                        }
                    }else{
                        return ErrorHelper::getNotFoundError('Post');
                    }
                }else{
                    return ErrorHelper::getNotFoundError($resource);
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
     * Get one post of task in each project
     * @date 04-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId
     * @param string $resource
     * @param string $resourceId
     * @param string $postId
     * @return json $response/$post
     */
    public function readOne($tenantId, $resource, $resourceId, $postId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $dataObj = $this->me->getResource();
            if($dataObj){
                $postObj = $this->me->getPost($postId);
                if($postObj){
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/'. $resource . '/' . $resourceId . '/posts/' . $postId;
                    $outputObj = $this->schema->renderOutput($postObj,$href,$user);
                    return response()->json($outputObj);
                }else{
                    return ErrorHelper::getNotFoundError('Post');
                }
            }else{
                return ErrorHelper::getNotFoundError($resource);
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
    * This function use for retrieve a listing of reply level 1 by the specified post.
    * @date 04-Aug-2017
    * @author Setha Thay <setha.thay@workevolve.com>
    * @param string $tenantId
    * @param string $resource
    * @param string $resourceId
    * @param string $postId
    * @return json A listing of reply level 1.
    */
    public function readAllReply($tenantId, $resource, $resourceId, $postId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $dataObj = $this->me->getResource();
            if($dataObj){
                $criteria = new CriteriaOption();
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
                $postObj = $this->me->getPost($postId);
                if($postObj){
                    $ds =   array_slice($postObj->replies->all(),$offset,$limit);
                    $dsHref = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/'. $resource . '/' . $resourceId . '/posts/' . $postId . '/replies';
                    $resultSet = $this->schemaReply->renderOutputDS($this->schemaReply, $ds, $dsHref, count($postObj->replies), $offset, $limit, $user);
                    return response()->json($resultSet);
                }else{
                    return ErrorHelper::getNotFoundError('Post');
                }
            }else{
                return ErrorHelper::getNotFoundError($resource);
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
     * Get one of reply level 1 of post of task in each project
     * @date 04-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId
     * @param string $resource
     * @param string $resourceId
     * @param string $postId
     * @param string $replyId
     * @return json $response/$post
     */
    public function readOneReply($tenantId, $resource, $resourceId, $postId, $replyId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $dataObj = $this->me->getResource();
            if($dataObj){
                $postObj = $this->me->getPost($postId);
                if($postObj){
                    $replyObj = $postObj->replies()->find($replyId);
                    if($replyObj){
                        $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/'. $resource . '/' . $resourceId . '/posts/' . $postId . '/replies/' . $replyObj->_id;
                        $result = $this->schemaReply->renderOutput($replyObj, $href, $user);
                        return response()->json($result);
                    }else{
                        return ErrorHelper::getNotFoundError('Reply');
                    }
                }else{
                    return ErrorHelper::getNotFoundError('Post');
                }
            }else{
                return ErrorHelper::getNotFoundError($resource);
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
     * Get one of reply level 2 of post of task in each project
     * @date 04-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $tenantId
     * @param string $resource
     * @param string $resourceId
     * @param string $postId
     * @param string $replyId
     * @param string $replyLevel2Id
     * @return json $response/$post
     */
    public function readOneReplyofReply($tenantId, $resource, $resourceId, $postId, $replyId, $replyLevel2Id){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $dataObj = $this->me->getResource();
            if($dataObj){
                $postObj = $this->me->getPost($postId);
                if($postObj){
                    $replyObj = $postObj->replies()->find($replyId);
                    if($replyObj){
                        $replyObj2 = $replyObj->replies()->find($replyLevel2Id);
                        if($replyObj2){
                            $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/'. $resource . '/' . $resourceId . '/posts/' . $postId . '/replies/' . $replyId . '/replies/' . $replyObj2->_id;
                            $result = $this->schemaReply->renderOutput($replyObj, $href, $user);
                            return response()->json($result);
                        }else{
                            return ErrorHelper::getNotFoundError('Reply');
                        }
                    }else{
                        return ErrorHelper::getNotFoundError('Reply');
                    }
                }else{
                    return ErrorHelper::getNotFoundError('Post');
                }
            }else{
                return ErrorHelper::getNotFoundError($resource);
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
