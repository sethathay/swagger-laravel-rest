<?php

/*
|--------------------------------------------------------------------------
| work Evolve Co,Ltd. Routes of Restful API
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for use.
| Follow the rule of creating route in a web app interface.
| If you are not sure of how to create, please ask your supervisor.
| 
| BE CAREFUL: YOU MUST CREATE ROUTE FOLLOW THE RULE
|
*/
header("Access-Control-Allow-Origin: *");
header("Access-Control-Max-Age: 1728000");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization, X-Requested-With, Session');

Route::group(['prefix' => env('APP_VERSION'),'middleware' => 'cors'], function(){
    Route::get('/sample', 'SampleController@index');
    /*
    |--------------------------------------------------------------------------
    | Resource: PROJECT
    |--------------------------------------------------------------------------
    */
    /**
     * Route to get projects information from collection projects
     * @author Kosal Tim <kosal.tim@workevolve.com>
     * @date 23-Aug-2016
     */
    Route::post('tenants/{tenantId}/projects/get','ProjectController@readAll');
    /**
     * Route to save project information to collection projects
     * @author Kosal Tim <kosal.tim@workevolve.com>
     * @date 21-Aug-2016
     */
    Route::post('tenants/{tenantId}/projects','ProjectController@create');
    /**
     * Route to update project information
     * @author Seng Sathya <sathya.seng@workevolve.com>
     * @date 07-Dec-2016
     */
    Route::post('tenants/{tenantId}/projects/{projectId}','ProjectController@update');
    /**
     * Route to get a project information from collection projects
     * @author Kosal Tim <kosal.tim@workevolve.com>
     * @date 23-Aug-2016
     */
    Route::get('tenants/{tenantId}/projects/{projectId}','ProjectController@readOne');
    /*
    |--------------------------------------------------------------------------
    | Resource: ADDRESS BOOK
    |--------------------------------------------------------------------------
    */
    /**
     * Route to get address book information from collection address books (Resource to get address book list)
     * @author Phou Lin <lin.phou@workevolve.com>
     * @date 15-Feb-2017
     */    
    Route::post('tenants/{tenantId}/addressbooks/get','AddressBookController@readAll');
    /**
     * Resource to get address book specific by id
     * @author Phou Lin <lin.phou@workevolve.com>
     * @date 15-Feb-2017
     */
    Route::get('tenants/{tenantId}/addressbooks/{addressBookId}','AddressBookController@readOne');
    /**
     * Resource to create a new address book record.
     * @author Phou Lin <lin.phou@workevolve.com>
     * @date 15-Feb-2017
     */
    Route::post('tenants/{tenantId}/addressbooks','AddressBookController@create');
    /**
     * Resource to update an address book information.
     * @author Phou Lin <lin.phou@workevolve.com>
     * @date 15-Feb-2017
     */
    Route::post('tenants/{tenantId}/addressbooks/{addressBookId}','AddressBookController@update');
    /**
     * Resource to disabled an address book record.
     * @author Phou Lin <lin.phou@workevolve.com>
     * @date 15-Feb-2017
     */
    Route::delete('tenants/{tenantId}/addressbooks/{addressBookId}','AddressBookController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: CUSTOMER
    |--------------------------------------------------------------------------
    */
    /**
     * Route to get customers information from collection address books
     * @author Kosal Tim <kosal.tim@workevolve.com>
     * @date 27-Aug-2016
     */
    Route::post('tenants/{tenantId}/customers/get','CustomerController@readAll');
    /**
     * Resource to get customer specific by id
     * @author Phou Lin <lin.phou@workevolve.com>
     * @date 15-Feb-2017
     */
    Route::get('tenants/{tenantId}/customers/{customerId}','CustomerController@readOne');
    /**
     * Resource to create a new customer record.
     * @author Phou Lin <lin.phou@workevolve.com>
     * @date 15-Feb-2017
     */
    Route::post('tenants/{tenantId}/customers','CustomerController@create');
    /**
     * Resource to update an customer information.
     * @author Phou Lin <lin.phou@workevolve.com>
     * @date 15-Feb-2017
     */
    Route::post('tenants/{tenantId}/customers/{customerId}','CustomerController@update');
    /**
     * Resource to remove an customer record.
     * @author Phou Lin <lin.phou@workevolve.com>
     * @date 15-Feb-2017
     */
    Route::delete('tenants/{tenantId}/customers/{customerId}','CustomerController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: TASK
    |--------------------------------------------------------------------------
    */
    /**
     * Route to get all tasks by knowing id of resource
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 10-Nov-2017
     */
    Route::post('tenants/{tenantId}/{resource}/{resourceId}/tasks/get','TaskController@readAll');
    /**
     * Route to get an task by id of resource
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 10-Nov-2017
     */
    Route::get('tenants/{tenantId}/{resource}/{resourceId}/tasks/{taskId}','TaskController@readOne');
    /**
     * Route to store task information by knowing id of resource
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 10-Nov-2017
     */
    Route::post('tenants/{tenantId}/{resource}/{resourceId}/tasks','TaskController@create');
    /**
     * Route to update task information by id of resource
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 10-Nov-2017
     */
    Route::post('tenants/{tenantId}/{resource}/{resourceId}/tasks/{taskId}','TaskController@update');
    /**
     * Route to remove task information by id of resource
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 10-Nov-2017
     */
    Route::delete('tenants/{tenantId}/{resource}/{resourceId}/tasks/{taskId}','TaskController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: POST
    |--------------------------------------------------------------------------
    */
    /**
     * Route use for save post in collection posts
     * @author Phou Lin <lin.phou@workevolve.com>
     * @date 28-sep-2016
     */
    Route::post('tenants/{tenantId}/{resource}/{resourceId}/posts','PostController@create');
    /**
     * Route use for update reply level 1 in collection posts
     * @author Phou Lin <lin.phou@workevolve.com>
     * @date 28-sep-2016
     */
    Route::post('tenants/{tenantId}/{resource}/{resourceId}/posts/{postId}/replies','PostController@createReply');
    /**
     * Route use for update reply level 2 in collection posts
     * @author Phou Lin <lin.phou@workevolve.com>
     * @date 28-sep-2016
     */
    Route::post('tenants/{tenantId}/{resource}/{resourceId}/posts/{postId}/replies/{replyId}/replies','PostController@createReplyofReply');
    /**
     * Route use for update post in collection posts
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 03-Aug-2017
     */
    Route::post('tenants/{tenantId}/{resource}/{resourceId}/posts/{postId}','PostController@update');
    /**
     * Route use for update reply of post in collection posts
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 04-Aug-2017
     */
    Route::post('tenants/{tenantId}/{resource}/{resourceId}/posts/{postId}/replies/{replyId}','PostController@updateReply');
    /**
     * Route use for update reply level 2 of post in collection posts
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 04-Aug-2017
     */
    Route::post('tenants/{tenantId}/{resource}/{resourceId}/posts/{postId}/replies/{replyId}/replies/{replyLevel2Id}','PostController@updateReplyofReply');
    /**
     * Provide all posts in an task in a project.
     * @author Phou Lin <lin.phou@workevolve.com>
     * @date 12-Sept-2016
     */
    Route::get('tenants/{tenantId}/{resource}/{resourceId}/posts','PostController@readAll');
    /**
     * Provide a post in an task in a project.
     * @author Phou Lin <lin.phou@workevolve.com>
     * @date 12-Sept-2016
     */
    Route::get('tenants/{tenantId}/{resource}/{resourceId}/posts/{postId}','PostController@readOne');
    /**
     * Provide all replies level 1 in a post in an task in a project.
     * @author Phou Lin <lin.phou@workevolve.com>
     * @date 12-Sept-2016
     */
    Route::get('tenants/{tenantId}/{resource}/{resourceId}/posts/{postId}/replies','PostController@readAllReply');
    /**
     * Provide a reply level 1 in a post in an task in a project.
     * @author Phou Lin <lin.phou@workevolve.com>
     * @date 12-Sept-2016
     */
    Route::get('tenants/{tenantId}/{resource}/{resourceId}/posts/{postId}/replies/{replyId}','PostController@readOneReply');
    /**
     * Provide a reply level 2 in a post in an task in a project.
     * @author Sathya Seng <sathya.seng@workevolve.com>
     * @date 12-Sept-2016
     */
    Route::get('tenants/{tenantId}/{resource}/{resourceId}/posts/{postId}/replies/{replyId}/replies/{replyLevel2Id}','PostController@readOneReplyofReply');
    /**
     * Provide all reply level 2 in a reply level 1 in a post in an task in a project.
     * @author Phou Lin <lin.phou@workevolve.com>
     * @date 12-Sept-2016
     */
    Route::get('tenants/{tenantId}/{resource}/{resourceId}/posts/{postId}/replies/{replyId}/replies','PostController@readAllReplyofReply');
    /*
    |--------------------------------------------------------------------------
    | Resource: UDC TYPE of weCloudFoundation
    |--------------------------------------------------------------------------
    */
    /**
     * Route retrieve a listing of the UDC Type
     * @author Im Chomnan <chomnan.im@workevolve.com>
     * @date 23-Aug-2016
     */
    Route::post('systems/{sysCode}/types/get','UdcTypeFoundationController@readAll');
    /**
     * Route retrieve the specified UDC Type
     * @author Im Chomnan <chomnan.im@workevolve.com>
     * @date 23-Aug-2016
     */
    Route::get('systems/{sysCode}/types/{typeId}','UdcTypeFoundationController@readOne');
    /**
     * Route store a newly created UDC Type
     * @author Im Chomnan <chomnan.im@workevolve.com>
     * @date 23-Aug-2016
     */
    Route::post('systems/{sysCode}/types','UdcTypeFoundationController@create');
    /**
     * Route update the specified UDC Type
     * @author Im Chomnan <chomnan.im@workevolve.com>
     * @date 23-Aug-2016
     */
    Route::post('systems/{sysCode}/types/{typeId}','UdcTypeFoundationController@update');
    /**
     * Route remove the specified UDC Type
     * @author Im Chomnan <chomnan.im@workevolve.com>
     * @date 23-Aug-2016
     */
    Route::delete('systems/{sysCode}/types/{typeId}','UdcTypeFoundationController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: UDC VALUE of weCloudFoundation
    |--------------------------------------------------------------------------
    */
    /**
     * Route retrieve a listing of the UDC Value specified by UDC Type
     * @author Im Chomnan <chomnan.im@workevolve.com>
     * @date 23-Aug-2016
     */
    Route::post('systems/{sysCode}/types/{typeId}/values/get','UdcValueFoundationController@readAll');
    /**
     * Route store a newly created UDC Value
     * @author Im Chomnan <chomnan.im@workevolve.com>
     * @date 23-Aug-2016
     */
    Route::post('systems/{sysCode}/types/{typeId}/values','UdcValueFoundationController@create');
    /**
     * Route update the specified UDC Value
     * @author Im Chomnan <chomnan.im@workevolve.com>
     * @date 23-Aug-2016
     */
    Route::post('systems/{sysCode}/types/{typeId}/values/{valueId}','UdcValueFoundationController@update');    
    /**
     * Route remove the specified UDC Value
     * @author Im Chomnan <chomnan.im@workevolve.com>
     * @date 23-Aug-2016
     */
    Route::delete('systems/{sysCode}/types/{typeId}/values/{valueId}','UdcValueFoundationController@remove');
    /**
     * Route retrieve a UDC Value specified by UDC Type
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 17-Jul-2017
     */
    Route::get('systems/{sysCode}/types/{typeId}/values/{valueId}','UdcValueFoundationController@readOne');
    /*
    |--------------------------------------------------------------------------
    | Resource: USER
    |--------------------------------------------------------------------------
    */
    /**
     * Route users retrieve a listing of user
     * @author Im Chomnan <chomnan.im@workevolve.com>
     * @date 06-Oct-2016
     */
    Route::post('users/get','UserController@readAll');
    /**
     * Route retrieve the specified user
     * @author Seng Sathya <sathya.seng@workevolve.com>
     * @date 17-Oct-2016
     */
    Route::get('users/{userId}','UserController@readOne');
    /**
     * Route update the specified user
     * @author Seng Sathya <sathya.seng@workevolve.com>
     * @date 17-Oct-2016
     */
    Route::post('users/{userId}','UserController@update');
    /*
    |--------------------------------------------------------------------------
    | Resource: INVITATION
    |--------------------------------------------------------------------------
    */
    /**
     * Route store invitee account,send email to notify invitee 
     * @author Im Chomnan <chomnan.im@workevolve.com>
     * @date 25-Nov-2016
     */
    Route::post('invitations','UserController@sendInvitation');
    /**
     * Route update (Resend or SaveUpdate) the specified invitee acount
     * @author Im Chomnan <chomnan.im@workevolve.com>
     * @date 24-Dec-2016
     */
    Route::post('invitations/{userId}','UserController@resendInvitation');
    /**
     * Route retrieve the specified register invitee acount base on token
     * @author Im Chomnan <chomnan.im@workevolve.com>
     * @date 12-Dec-2016
     */
    Route::get('invitations/{tokenId}','UserController@getInvitation');
    /**
     * Route update the specified register invitee acount base on token
     * @author Im Chomnan <chomnan.im@workevolve.com>
     * @date 15-Dec-2016
     */
    Route::post('invitations/{tokenId}/accept','UserController@acceptInvitation');
    /**
     * Route remove the specified weCloudApp from the specified user account
     * @author Im Chomnan <chomnan.im@workevolve.com>
     * @date 17-Oct-2016
     */
    Route::delete('invitations/{userId}','UserController@removeInvitation');
    /*
    |--------------------------------------------------------------------------
    | Resource: UDC TYPE of weCloudProjectTracking
    |--------------------------------------------------------------------------
    */
    /**
     * Route retrieve all udcs type in weCloudProjectTracking 
     * @author Chomnan Im <chomnan.im@workevolve.com> 
     * @date 23-Aug-2016 
     */
    Route::post('tenants/{tenantId}/systems/{sysCode}/types/get','UdcTypeController@readAll');
    /**
     * Route retrieve a udcs type in weCloudProjectTracking
     * @author Chomnan Im <chomnan.im@workevolve.com> 
     * @date 23-Aug-2016 
     */
    Route::get('tenants/{tenantId}/systems/{sysCode}/types/{typeId}','UdcTypeController@readOne');
    /**
     * Route to store a new udcs type in weCloudProjectTracking
     * @author Chomnan Im <chomnan.im@workevolve.com> 
     * @date 23-Aug-2016 
     */
    Route::post('tenants/{tenantId}/systems/{sysCode}/types','UdcTypeController@create');
    /**
     * Route to update a udcs type in weCloudProjectTracking
     * @author Chomnan Im <chomnan.im@workevolve.com> 
     * @date 23-Aug-2016 
     */
    Route::post('tenants/{tenantId}/systems/{sysCode}/types/{typeId}','UdcTypeController@update');
    /**
     * Route to delete a udcs type in weCloudProjectTracking
     * @author Chomnan Im <chomnan.im@workevolve.com> 
     * @date 23-Aug-2016 
     */
    Route::delete('tenants/{tenantId}/systems/{sysCode}/types/{typeId}','UdcTypeController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: UDC VALUE of weCloudProjectTracking
    |--------------------------------------------------------------------------
    */
    /**
     * Route to retrieve all udcs value of a udcs type in weCloudProjectTracking
     * @author Chomnan Im <chomnan.im@workevolve.com> 
     * @date 23-Aug-2016 
     */
    Route::post('tenants/{tenantId}/systems/{sysCode}/types/{typeId}/values/get','UdcValueController@readAll');
    /**
     * Route to store a udcs value of a udcs type in weCloudProjectTracking
     * @author Chomnan Im <chomnan.im@workevolve.com> 
     * @date 23-Aug-2016 
     */
    Route::post('tenants/{tenantId}/systems/{sysCode}/types/{typeId}/values','UdcValueController@create');
    /**
     * Route to retrieve a udcs value of a udcs type in weCloudProjectTracking
     * @author Chomnan Im <chomnan.im@workevolve.com> 
     * @date 23-Aug-2016 
     */
    Route::get('tenants/{tenantId}/systems/{sysCode}/types/{typeId}/values/{valueId}','UdcValueController@readOne');
    /**
     * Route to update a udcs value of a udcs type in weCloudProjectTracking
     * @author Chomnan Im <chomnan.im@workevolve.com> 
     * @date 23-Aug-2016 
     */
    Route::post('tenants/{tenantId}/systems/{sysCode}/types/{typeId}/values/{valueId}','UdcValueController@update');
    /**
     * Route to delete a udcs value of a udcs type in weCloudProjectTracking
     * @author Chomnan Im <chomnan.im@workevolve.com> 
     * @date 23-Aug-2016 
     */
    Route::delete('tenants/{tenantId}/systems/{sysCode}/types/{typeId}/values/{valueId}','UdcValueController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: TENANT
    |--------------------------------------------------------------------------
    */
    /**
     * Route Retrive the specified tenant
     * @author Seng Sathya <sathya.seng@workevolve.com>
     * @date 17-Oct-2016
     */
    Route::get('tenants/{tenantId}','TenantController@readOne');
    /**
    * register new tenant (NEW/EXISTING ACCOUNT) to collection tenants  
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 06-Jun-2017
    */
    Route::post('tenants/register','TenantController@register');
    /**
    * tenant subscribe pricing plan using stripe payment method
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 12-Jun-2017
    */
    Route::post('tenants/{tenantId}/subscription','TenantController@subscribe');
    /**
    * Check subscription status of tenant based on id
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 14-Jun-2017
    */
    Route::get('tenants/{tenantId}/subscription/status','TenantController@checkSubscriptionStatus');
    /**
    * Change subscription plan (Downgrade/Upgrade)
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 14-Jun-2017
    */
    Route::post('tenants/{tenantId}/subscription/swap','TenantController@changePlan');
    /**
    * Get payment methods information
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 15-Jun-2017
    */
    Route::get('tenants/{tenantId}/paymentmethods','TenantController@getPaymentMethods');
    /**
    * Tenant add new payment method (credit card) using stripe
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 16-Jun-2017
    */
    Route::post('tenants/{tenantId}/paymentmethods','TenantController@addPaymentMethods');
    /**
    * Tenant delete payment method (credit card) using stripe
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 19-Jun-2017
    */
    Route::delete('tenants/{tenantId}/paymentmethods/{cardId}','TenantController@removePaymentMethods');
    /**
    * Make default payment method (credit card) using stripe
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 19-Jun-2017
    */
    Route::post('tenants/{tenantId}/paymentmethods/{cardId}','TenantController@makeDefaultPaymentMethods');
    /**
    * Update payment method (credit card) using stripe
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 19-Jun-2017
    */
    Route::post('tenants/{tenantId}/paymentmethods/{cardId}/update','TenantController@updatePaymentMethods');
    /**
    * Get billing invoice from stripe
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 20-Jun-2017
    */
    Route::get('tenants/{tenantId}/billing','TenantController@getBilling');
    /**
    * Download PDF invoice
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 20-Jun-2017
    */
    Route::post('tenants/{tenantId}/billing/download','TenantController@downloadInvoice');
    /*
    |--------------------------------------------------------------------------
    | Resource: FEEDBACK
    |--------------------------------------------------------------------------
    */
    /**
     * Route Retrive all feedbacks of project
     * @author Seng Sathya <sathya.seng@workevolve.com>
     * @date 28-nov-2016
     */
    Route::get('tenants/{tenantId}/projects/{projectId}/feedbacks','FeedbackController@readAll');
    /**
     * Route retrieve feedback 
     * @author Seng Sathya <sathya.seng@workevolve.com>
     * @date 28-nov-2016
     */
    Route::get('tenants/{tenantId}/projects/{projectId}/feedbacks/{feedbackId}','FeedbackController@readOne');
    /**
     * Route Store feedback
     * @author Seng Sathya <sathya.seng@workevolve.com>
     * @date 28-nov-2016
     */
    Route::post('tenants/{tenantId}/projects/{projectId}/feedbacks','FeedbackController@create');
    /**
     * Route update feedback
     * @author Setha Tha <setha.thay@workevolve.com>
     * @date 02-Aug-2017
     */
    Route::post('tenants/{tenantId}/projects/{projectId}/feedbacks/{feedbackId}','FeedbackController@update');
    /**
     * Route Store feedback
     * @author Seng Sathya <sathya.seng@workevolve.com>
     * @date 28-nov-2016
     */
    Route::post('tenants/{tenantId}/projects/{projectId}/feedbacks/{feedbackId}/replies','FeedbackController@createReply');
    /**
     * Route update reply of feedback
     * @author Setha Tha <setha.thay@workevolve.com>
     * @date 02-Aug-2017
     */
    Route::post('tenants/{tenantId}/projects/{projectId}/feedbacks/{feedbackId}/replies/{replyLevel1Id}','FeedbackController@updateReply');
    /**
     * Route retrieve feedback reply level 1
     * @author Seng Sathya <sathya.seng@workevolve.com>
     * @date 28-nov-2016
     */
    Route::get('tenants/{tenantId}/projects/{projectId}/feedbacks/{feedbackId}/replies/{replyLevel1Id}','FeedbackController@readOneReply');
    /**
     * Route retrieve replies level 1 of feedback
     * @author Seng Sathya <sathya.seng@workevolve.com>
     * @date 11-oct-2016
     */
    Route::get('tenants/{tenantId}/projects/{projectId}/feedbacks/{feedbackId}/replies','FeedbackController@readAllReply');
    /**
     * Route Store reply level 2 of feedback
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 01-Aug-2017
     */
    Route::post('tenants/{tenantId}/projects/{projectId}/feedbacks/{feedbackId}/replies/{replyLevel1Id}/replies','FeedbackController@createReplyofReply');
    /**
     * Route update reply of reply of feedback
     * @author Setha Tha <setha.thay@workevolve.com>
     * @date 03-Aug-2017
     */
    Route::post('tenants/{tenantId}/projects/{projectId}/feedbacks/{feedbackId}/replies/{replyLevel1Id}/replies/{replyLevel2Id}','FeedbackController@updateReplyofReply');
    /**
     * Route retrieve replies level 2 of feedback
     * @author Seng Sathya <sathya.seng@workevolve.com>
     * @date 12-oct-2016
     */
    Route::get('tenants/{tenantId}/projects/{projectId}/feedbacks/{feedbackId}/replies/{replyLevel1Id}/replies','FeedbackController@readAllReplyofReply');
    /*
    |--------------------------------------------------------------------------
    | Resource: RATING
    |--------------------------------------------------------------------------
    */
    /**
     * Route Retrive one rating or project by user id
     * @author Seng Sathya <sathya.seng@workevolve.com> 
     * @date 12-oct-2016
     */
    Route::get('tenants/{tenantId}/{resource}/{resourceId}/ratings/{userId}','RateController@readOne');
    /**
     * Route Store rating
     * @author Seng Sathya <sathya.seng@workevolve.com> 
     * @date 12-oct-2016
     */
    Route::post('tenants/{tenantId}/{resource}/{resourceId}/ratings','RateController@create');
    /**
     * Route Retrive one rating or project by user id
     * @author Seng Sathya <sathya.seng@workevolve.com> 
     * @date 12-oct-2016
     */
    Route::post('tenants/{tenantId}/{resource}/{resourceId}/ratings/{userId}','RateController@update');
    /*
    |--------------------------------------------------------------------------
    | Resource: UPLOAD
    |--------------------------------------------------------------------------
    */
    /**
     * Route Upload Templorary files
     * @author Seng Sathya <sathya.seng@workevolve.com> 
     * @date 12-oct-2016
     */
    Route::post('tenants/{tenantId}/upload','UploadController@upload');
    /**
     * delete file in aws by ID  
     * @author Seng Sathya <sathya.seng@workevolve.com>  
     * @date 07-dec-2016   
     */
    Route::delete('tenants/{tenantId}/upload/{fileId}','UploadController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: OBJECT LIBRARY - COMMON INFORMATION OF OBJECT LIBRARY
    |--------------------------------------------------------------------------
    */
    /**
    * listing common information of object library from collection object library  
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 07-Apr-2017
    */
    Route::post('objectlibraries/get','ObjLibraryController@readAll');
    /**
    * Saving common information of object library to collection object library  
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 05-Apr-2017
    */
    Route::post('objectlibraries','ObjLibraryController@create');
    /**
    * updating common information of object library to collection object library  
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 05-Apr-2017
    */
    Route::post('objectlibraries/{objId}','ObjLibraryController@update');
    /**
    * Getting one of object library from collection
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 12-Dec-2017
    */
    Route::get('objectlibraries/{objId}','ObjLibraryController@readOne');
    /**
     * Resource to remove object library
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 12-Dec-2017
     */
    Route::delete('objectlibraries/{objId}','ObjLibraryController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: OBJECT LIBRARY - SCHEMA OBJECT
    |--------------------------------------------------------------------------
    */
    /**
    * listing all schemas in object library from collection object library  
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 10-Apr-2017
    */
    Route::post('schemas/get','SchemaController@readAll');
    /**
    * Saving json of schema builder to collection object library  
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 03-Apr-2017
    */
    Route::post('schemas','SchemaController@create');
    /**
    * Updating schema builder to collection object library  
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 05-Apr-2017
    */
    Route::post('schemas/{schemaId}','SchemaController@update');
    /**
    * list only one schema in object library from collection object library  
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 10-Apr-2017
    */
    Route::get('schemas/{schemaId}','SchemaController@readOne');
    /**
     * Resource to remove schema
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 29-Apr-2017
     */
    Route::delete('schemas/{schemaId}','SchemaController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: OBJECT LIBRARY - PROGRAM OBJECT
    |--------------------------------------------------------------------------
    */
    /**
    * listing all programs in object library from collection object library  
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 10-Apr-2017
    */
    Route::post('programs/get','ProgramController@readAll');
    /**
    * Saving json of po builder to collection object library
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 04-Apr-2017
    */
    Route::post('programs','ProgramController@create');
    /**
    * Updating json of po builder to collection object library
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 05-Apr-2017
    */
    Route::post('programs/{proId}','ProgramController@update');
    /**
    * list only one program in object library from collection object library  
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 10-Apr-2017
    */
    Route::get('programs/{proId}','ProgramController@readOne');
    /**
     * Resource to remove program
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 29-Apr-2017
     */
    Route::delete('programs/{proId}','ProgramController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: FORM
    |--------------------------------------------------------------------------
    */
    /**
    * listing form of program from collection object library  
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Aug-2017
    */
    Route::post('programs/{proId}/forms/get','FormController@readAll');
    /**
    * Saving json of form to collection object library
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Aug-2017
    */
    Route::post('programs/{proId}/forms','FormController@create');
    /**
    * Updating json of form to collection object library
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Aug-2017
    */
    Route::post('programs/{proId}/forms/{formId}','FormController@update');
    /**
    * listing one form of program from collection object library
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Aug-2017
    */
    Route::get('programs/{proId}/forms/{formId}','FormController@readOne');
    /**
     * Resource to remove form of object library
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 11-Aug-2017
     */
    Route::delete('programs/{proId}/forms/{formId}','FormController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: VERSION
    |--------------------------------------------------------------------------
    */
    /**
    * listing version of object library (Program/Report) from collection object library  
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 10-Apr-2017
    */
    Route::post('tenants/{tenantId}/programs/{proId}/versions/get','VersionController@readAll');
    /**
    * Saving json of version to collection object library
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 08-Apr-2017
    */
    Route::post('tenants/{tenantId}/programs/{proId}/versions','VersionController@create');
    /**
    * Updating json of version to collection object library
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 08-Apr-2017
    */
    Route::post('tenants/{tenantId}/programs/{proId}/versions/{versionId}','VersionController@update');
    /**
    * listing one version of object library (Program/Report) from collection object library
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 16-May-2017
    */
    Route::get('tenants/{tenantId}/programs/{proId}/versions/{versionCode}','VersionController@readOne');
    /**
     * Resource to remove verion of object library
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 29-Apr-2017
     */
    Route::delete('tenants/{tenantId}/programs/{proId}/versions/{versionId}','VersionController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: MENUS
    |--------------------------------------------------------------------------
    */
    /**
    * listing information of menus from collection menus
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Apr-2017
    */
    Route::post('tenants/{tenantId}/menus/get','MenuController@readAll');
    /**
    * listing one menu from collection menus
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 06-Nov-2017
    */
    Route::get('tenants/{tenantId}/menus/{menuId}','MenuController@readOne');
    /**
    * Saving json of menu master to collection menus
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Apr-2017
    */
    Route::post('tenants/{tenantId}/menus','MenuController@create');
    /**
    * Updating json of menu master to collection menus
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 27-Apr-2017
    */
    Route::post('tenants/{tenantId}/menus/{menuId}','MenuController@update');
    /**
     * Resource to remove menu
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 29-Apr-2017
     */
    Route::delete('tenants/{tenantId}/menus/{menuId}','MenuController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: MENU SELECTION
    |--------------------------------------------------------------------------
    */
    /**
    * listing information of menu selection from collection menus
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 13-Apr-2017
    */
    Route::post('tenants/{tenantId}/menus/{menuId}/menuselections/get','MenuSelectionController@readAll');
    /**
    * Saving json of menu selection to collection menus
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 12-Apr-2017
    */
    Route::post('tenants/{tenantId}/menus/{menuId}/menuselections','MenuSelectionController@create');
    /**
    * Updating json of menu selection to collection menus
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 27-Apr-2017
    */
    Route::post('tenants/{tenantId}/menus/{menuId}/menuselections/{selectionId}','MenuSelectionController@update');
    /**
     * Resource to remove menu selection
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 29-Apr-2017
     */
    Route::delete('tenants/{tenantId}/menus/{menuId}/menuselections/{selectionId}','MenuSelectionController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: Lookup
    |--------------------------------------------------------------------------
    */
    /**
     * Resource to lookup for datasource by program name and form name
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 14-Aug-2017
     */
    Route::post('tenants/{tenantId}/lookup','LookUpController@readAll');
    /*
    |--------------------------------------------------------------------------
    | Resource: DATA REVISION
    |--------------------------------------------------------------------------
    */
    /**
     * Returns the data about the specific revision on an item
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 29-Nov-2017
     */
    Route::get('tenants/{tenantId}/{resource}/{resourceId}/revisions/{revision}', 'RevisionController@readOne');
    /**
     * Returns the data about the event
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 04-Dec-2017
     */
    Route::get('tenants/{tenantId}/events/{event}', 'RevisionController@readEvent');
    /**
     * Reverts the item to the values in the given revision. This will undo any changes made after the given revision.
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 30-Nov-2017
     */
    Route::post('tenants/{tenantId}/{resource}/{resourceId}/revisions/{revision}/revert', 'RevisionController@revert');
    /**
     * Reverts the item to the values in the given event. This will undo any changes made after the given event.
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 04-Dec-2017
     */
    Route::post('tenants/{tenantId}/events/{event}/revert', 'RevisionController@revertEvent');
    /**
     * Returns all the revisions that have been made to an item
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 30-Nov-2017
     */
    Route::get('tenants/{tenantId}/{resource}/{resourceId}/revisions', 'RevisionController@readAll');
    /**
     * Returns all the events that have been made
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 04-Dec-2017
     */
    Route::get('tenants/{tenantId}/events', 'RevisionController@readEvents');
    /**
     * Returns the difference in fields values between the two revisions
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 30-Nov-2017
     */
    Route::get('tenants/{tenantId}/{resource}/{resourceId}/revisions/{revisionFrom}/{revisionTo}', 'RevisionController@readDiff');
    /*
    |--------------------------------------------------------------------------
    | Resource: ROLES
    |--------------------------------------------------------------------------
    */
    /**
    * listing information of roles from collection roles
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 07-Dec-2017
    */
    Route::post('tenants/{tenantId}/roles/get','RoleController@readAll');
    /**
    * listing one role from collection roles
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 07-Dec-2017
    */
    Route::get('tenants/{tenantId}/roles/{role}','RoleController@readOne');
    /**
    * Saving json of roles master to collection roles
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 07-Dec-2017
    */
    Route::post('tenants/{tenantId}/roles','RoleController@create');
    /**
    * Updating json of roles to collection roles
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 07-Dec-2017
    */
    Route::post('tenants/{tenantId}/roles/{role}','RoleController@update');
    /**
     * Resource to remove role
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 07-Dec-2017
     */
    Route::delete('tenants/{tenantId}/roles/{role}','RoleController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: ROLES FOR A SPECIFIC USER
    |--------------------------------------------------------------------------
    */
    /**
    * listing information of roles for a specific user from collection roles
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 07-Dec-2017
    */
    Route::post('tenants/{tenantId}/userroles/get','UserRoleController@readAll');
    /**
    * listing one role for a specific user from collection roles
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 07-Dec-2017
    */
    Route::get('tenants/{tenantId}/userroles/{role}','UserRoleController@readOne');
    /**
    * Saving json of roles master of specific user to collection roles
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 07-Dec-2017
    */
    Route::post('tenants/{tenantId}/userroles','UserRoleController@create');
    /**
    * Updating json of roles for a specific user to collection roles
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 07-Dec-2017
    */
    Route::post('tenants/{tenantId}/userroles/{role}','UserRoleController@update');
    /**
     * Resource to remove role for a specific user
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 07-Dec-2017
     */
    Route::delete('tenants/{tenantId}/userroles/{role}','UserRoleController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: ROLE LIBRARY - COMMON INFORMATION OF ROLE LIBRARY
    |--------------------------------------------------------------------------
    */
    /**
    * listing common information of role library
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 12-Dec-2017
    */
    Route::post('tenants/{tenantId}/rolelibraries/get','RoleLibraryController@readAll');
    /**
    * Saving common information of role library  
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 12-Dec-2017
    */
    Route::post('tenants/{tenantId}/rolelibraries','RoleLibraryController@create');
    /**
    * updating common information of role library
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 12-Dec-2017
    */
    Route::post('tenants/{tenantId}/rolelibraries/{role}','RoleLibraryController@update');
    /**
    * Getting one of role library from collection
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 12-Dec-2017
    */
    Route::get('tenants/{tenantId}/rolelibraries/{role}','RoleLibraryController@readOne');
    /**
     * Resource to remove role library
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 12-Dec-2017
     */
    Route::delete('tenants/{tenantId}/rolelibraries/{role}','RoleLibraryController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: ROW PERMISSION OF ROLE
    |--------------------------------------------------------------------------
    */
    /**
    * listing information of row permission
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Dec-2017
    */
    Route::post('tenants/{tenantId}/roles/{role}/rowpermission/get','RowPermissionController@readAll');
    /**
    * Saving json of row permission
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Dec-2017
    */
    Route::post('tenants/{tenantId}/roles/{role}/rowpermission','RowPermissionController@create');
    /**
    * Updating json of row permission
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Dec-2017
    */
    Route::post('tenants/{tenantId}/roles/{role}/rowpermission/{permissionId}','RowPermissionController@update');
    /**
    * Getting one of row permission from collection
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 12-Dec-2017
    */
    Route::get('tenants/{tenantId}/roles/{role}/rowpermission/{permissionId}','RowPermissionController@readOne');
    /**
     * Resource to remove row permission
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 11-Dec-2017
     */
    Route::delete('tenants/{tenantId}/roles/{role}/rowpermission/{permissionId}','RowPermissionController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: COLUMN PERMISSION OF ROLE
    |--------------------------------------------------------------------------
    */
    /**
    * listing information of column permission
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Dec-2017
    */
    Route::post('tenants/{tenantId}/roles/{role}/columnpermission/get','ColumnPermissionController@readAll');
    /**
    * Saving json of column permission
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Dec-2017
    */
    Route::post('tenants/{tenantId}/roles/{role}/columnpermission','ColumnPermissionController@create');
    /**
    * Updating json of column permission
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Dec-2017
    */
    Route::post('tenants/{tenantId}/roles/{role}/columnpermission/{permissionId}','ColumnPermissionController@update');
    /**
    * Getting one of column permission from collection
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 12-Dec-2017
    */
    Route::get('tenants/{tenantId}/roles/{role}/columnpermission/{permissionId}','ColumnPermissionController@readOne');
    /**
     * Resource to remove column permission
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 11-Dec-2017
     */
    Route::delete('tenants/{tenantId}/roles/{role}/columnpermission/{permissionId}','ColumnPermissionController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: ACTION PERMISSION OF ROLE
    |--------------------------------------------------------------------------
    */
    /**
    * listing information of action permission
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Dec-2017
    */
    Route::post('tenants/{tenantId}/roles/{role}/actionpermission/get','ActionPermissionController@readAll');
    /**
    * Saving json of action permission
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Dec-2017
    */
    Route::post('tenants/{tenantId}/roles/{role}/actionpermission','ActionPermissionController@create');
    /**
    * Updating json of action permission
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Dec-2017
    */
    Route::post('tenants/{tenantId}/roles/{role}/actionpermission/{permissionId}','ActionPermissionController@update');
    /**
    * Getting one of action permission from collection
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 12-Dec-2017
    */
    Route::get('tenants/{tenantId}/roles/{role}/actionpermission/{permissionId}','ActionPermissionController@readOne');
    /**
     * Resource to remove action permission
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 11-Dec-2017
     */
    Route::delete('tenants/{tenantId}/roles/{role}/actionpermission/{permissionId}','ActionPermissionController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: APPLICATION PERMISSION OF ROLE
    |--------------------------------------------------------------------------
    */
    /**
    * listing information of application permission
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Dec-2017
    */
    Route::post('tenants/{tenantId}/roles/{role}/applicationpermission/get','ApplicationPermissionController@readAll');
    /**
    * Saving json of application permission
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Dec-2017
    */
    Route::post('tenants/{tenantId}/roles/{role}/applicationpermission','ApplicationPermissionController@create');
    /**
    * Updating json of application permission
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 11-Dec-2017
    */
    Route::post('tenants/{tenantId}/roles/{role}/applicationpermission/{permissionId}','ApplicationPermissionController@update');
    /**
    * Getting one of application permission from collection
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 12-Dec-2017
    */
    Route::get('tenants/{tenantId}/roles/{role}/applicationpermission/{permissionId}','ApplicationPermissionController@readOne');
    /**
     * Resource to remove application permission
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 11-Dec-2017
     */
    Route::delete('tenants/{tenantId}/roles/{role}/applicationpermission/{permissionId}','ApplicationPermissionController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: GROUP SECURITY
    |--------------------------------------------------------------------------
    */
    /**
    * listing information of group security from collection roles
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 14-Dec-2017
    */
    Route::post('tenants/{tenantId}/groupsecurity/get','GroupSecurityController@readAll');
    /**
    * listing one group security from collection roles
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 14-Dec-2017
    */
    Route::get('tenants/{tenantId}/groupsecurity/{groupId}','GroupSecurityController@readOne');
    /**
    * Saving json of group security master to collection roles
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 14-Dec-2017
    */
    Route::post('tenants/{tenantId}/groupsecurity','GroupSecurityController@create');
    /**
    * Updating json of group security to collection roles
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 14-Dec-2017
    */
    Route::post('tenants/{tenantId}/groupsecurity/{groupId}','GroupSecurityController@update');
    /**
     * Resource to remove group security
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 14-Dec-2017
     */
    Route::delete('tenants/{tenantId}/groupsecurity/{groupId}','GroupSecurityController@remove');
    /*
    |--------------------------------------------------------------------------
    | Resource: USER SECURITY
    |--------------------------------------------------------------------------
    */
    /**
    * listing information of user security from collection roles
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 14-Dec-2017
    */
    Route::post('tenants/{tenantId}/usersecurity/get','UserSecurityController@readAll');
    /**
    * listing one user security from collection roles
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 14-Dec-2017
    */
    Route::get('tenants/{tenantId}/usersecurity/{userId}','UserSecurityController@readOne');
    /**
    * Saving json of user security master to collection roles
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 14-Dec-2017
    */
    Route::post('tenants/{tenantId}/usersecurity','UserSecurityController@create');
    /**
    * Updating json of user security to collection roles
    * @author Setha Thay <setha.thay@workevolve.com>  
    * @date 14-Dec-2017
    */
    Route::post('tenants/{tenantId}/usersecurity/{userId}','UserSecurityController@update');
    /**
     * Resource to remove user security
     * @author Setha Thay <setha.thay@workevolve.com>
     * @date 14-Dec-2017
     */
    Route::delete('tenants/{tenantId}/usersecurity/{userId}','UserSecurityController@remove');
});