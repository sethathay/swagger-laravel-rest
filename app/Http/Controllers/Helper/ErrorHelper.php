<?php
/**
 * This file is responsible for error and error related information requests
 * and contains of all static function using across application.
 * 
 * LICENSE: Some license information
 * 
 * @category name
 * @package name
 * @copyright (c) 2016, work Evolve
 * @license http://URL name
 * @version string
 * @since version
 */

namespace App\Http\Controllers\Helper;

    /**
    * Class ErrorHelper is used to assist and support developers 
    * in solving prolems as a whole related to error message only.
    * 
    * @since version 1.0
    * @deprecated since version 0.0
    */
class ErrorHelper
{   
    const STATUS        =   'status';
    const CODE          =   'code';
    const MESSAGE       =   'message';
    const DEVMESSAGE    =   'developerMessage';
    const APPCODE       =   '0101';

    /**
     * Show error message of Offset
     * @date 24-Aug-2016
     * @author Kosal Tim <kosal.tim@workevolve.com>
     * 
     * @return json Error message of offset with status code 400
     */
    public static function getOffsetError()
    {
        $status             =   400;
        $code               =   400;
        $message            =   "offset query parameter value is invalid or an unexpected type.";
        $devMessage         =   "offset query parameter value is invalid or an unexpected type.";
        
        return self::getErrorMessage($status, $code, $message, $devMessage);
    }
    
    /**
     * Show error message of Limit
     * @date 24-Aug-2016
     * @author Kosal Tim <kosal.tim@workevolve.com>
     * 
     * @return json Error message of limit with status code 400
     */
    public static function getLimitError()
    {
        $status             =   400;
        $code               =   400;
        $message            =   "limit query parameter value is invalid or an unexpected type.";
        $devMessage         =   "limit query parameter value is invalid or an unexpected type.";
        
        return self::getErrorMessage($status, $code, $message, $devMessage);
    }
    /**
     * Show unauthrize message 
     * @date 5-sep-2016
     * @author Kosal Tim <kosal.tim@workevolve.com>
     * 
     * @return json Unathorize of limit with status code 401
     */
    public static function getErrorUnauthorize()
    {
        $status             =   401;
        $code               =   401;
        $message            =   "Your don't have premission to get data.";
        $devMessage         =   "User query no permission.";
        
        return self::getErrorMessage($status, $code, $message, $devMessage);
    }
    /**
     * Show exception message of any exception
     * @date 24-Aug-2016
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $exceptionMessage is parameter of exception message
     * 
     * @return json Error message of exception with status code 400
     */
    public static function getExceptionError($exceptionMessage)
    {
        return self::getErrorMessage(400, 2108, $exceptionMessage, $exceptionMessage);
    }
    /**
     * Get standard error message format
     * @date 25-Aug-2016
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param int $httpStatus is standard code of HTTP status
     * @param int $code is internal code of our plateform
     * @param string $message is error message for normal user
     * @param string $devMessage is error message for developer user
     * 
     * @return json Standard error message format with standard HTTP status code
     */
    private static function getErrorMessage($httpStatus,$code,$message,$devMessage)
    {
        $errorMessage = array(
                                self::STATUS        =>  $httpStatus,
                                self::CODE          =>  $code,
                                self::MESSAGE       =>  $message,
                                self::DEVMESSAGE    =>  $devMessage
                            );
        return response()->json($errorMessage)->setStatusCode($httpStatus);
    }
    /**
     * Get resource not found error message.
     * 
     * @date 24-Aug-2016
     * 
     * @author Chomnan Im <chomnan.im@workevolve.com>
     * 
     * @param string $name Name of collection.
     * 
     * @return json resource not found error message.
     */
    public static function getNotFoundError($name)
    {
        $status             =   404;
        $code               =   2107;
        $message            =   "The requested resource does not exist.";
        $devMessage         =   $name.' not found in the database.';
        
        return self::getErrorMessage($status, $code, $message, $devMessage);
    }
    
    /**
     * Get error message of you were authenticated but lacking required privileges
     * @date 20-Oct-2016
     * @author Chomnan Im <chomnan.im@workevolve.com>
     * @return json error message of you were authenticated but lacking required privileges
     */
    public static function getForbiddenError(){
        $status             =   403;
        $code               =   403;
        $message            =   "Sorry, you're not allowed to do that.";
        $devMessage         =   "Sorry, you're not allowed to do that.";
           
        return self::getErrorMessage($status, $code, $message, $devMessage);  
    }
    
    /**
     * Get error message of missing or invalid request body data.
     * 
     * @date 24-Aug-2016
     * 
     * @author Chomnan Im <chomnan.im@workevolve.com>
     * 
     * @return json error message of missing or invalid request body data.
     */
    public static function getRequestBodyError()
    {
        $status             =   400;
        $code               =   400;
        $message            =   "Missing or invalid request body.";
        $devMessage         =   'Missing or invalid HTTP request body.  Please ensure the HTTP request contains a content body'
                                .' and the content is formatted correctly.';
           
        return self::getErrorMessage($status, $code, $message, $devMessage);
    }
    
    /**
     * Get error message of expansion
     * 
     * @date 26-Aug-2016
     * @author Setha Thay <setha.thay@workevolve.com>
     * 
     * @param string $resource restful api resource
     * @param string $expand expansion name
     * @return json error message of expansion
     */
    public static function getErrorExpansion($resource,$expand)
    {
        $status     =   400;
        $code       =   400;
        $message    =   $resource . " " . $expand . " does not support expansion.";
        $devMessage =   $resource . " " . $expand . " does not support expansion.";
        
        return self::getErrorMessage($status, $code, $message, $devMessage);
    }
    
    /**
     * validate user apps
     * 
     * @date 18-Nov-2016
     * @author Sathya Seng <sathya.seng@workevolve.com>
     * 
     * @param string $tenant tenant of user
     * @return json error message of forbiden
     */
    public static function validateUserApp($tenant)
    {
        //Validate weCloudApp of user request
        
        $appCode              = 'PROJ'; 
        $currentAppOfTenant =   null;
        
        foreach($tenant['apps'] as $app)
        {
            if($app['app']['code']== $appCode)
            {
                $currentAppOfTenant = $app; break;
            }
        }
        
        return $currentAppOfTenant;
    }
    
    /**
     * Get error message of email address already exist
     * @date 25-Nov-2016
     * @author Chomnan Im <chomnan.im@workevolve.com>
     * @return json error message of email address already exist
     */
    public static function getEmailExistError(){
        $status             =   400;
        $code               =   2101;
        $message            =   "A user with that email address already exists for this environment.";
        $devMessage         =   "A user with that email address already exists for this environment.";
           
        return self::getErrorMessage($status, $code, $message, $devMessage);  
    }
    
    /**
     * Get error message of invalid token
     * @date 12-Dec-2016
     * @author Chomnan Im <chomnan.im@workevolve.com>
     * @return json error message of invalid token
     */
    public static function getInvalidTokenError(){
        $status             =   404;
        $code               =   2104;
        $message            =   "The invitation code is invalid and this page could not be loaded as a result. Please try again.";
        $devMessage         =   "The invitation code is invalid and this page could not be loaded as a result. Please try again.";
           
        return self::getErrorMessage($status, $code, $message, $devMessage);  
    }
    /**
     * Get error message of token is expired
     * @date 12-Dec-2016
     * @author Chomnan Im <chomnan.im@workevolve.com>
     * @return json error message of token is expired
     */
    public static function getExpiredTokenError($organization){
        $status             =   400;
        $code               =   400;
        $message            =   "This invitation has expired. Please contact your $organization to receive a new invitation.";
        $devMessage         =   "This invitation has expired. Please contact your $organization to receive a new invitation.";
           
        return self::getErrorMessage($status, $code, $message, $devMessage);  
    }
    
    /**
     * Get error message of account is already activated
     * @date 12-Dec-2016
     * @author Chomnan Im <chomnan.im@workevolve.com>
     * @return json error message of account is already activated
     */
    public static function getAccountActivatedError($application,$organization,$email){
        $status             =   400;
        $code               =   2102;
        $message            =   "Looks like you already have a $application account for $email. Login as usual to accept your invite to $organization.";
        $devMessage         =   "Account is already activated.";
           
        return self::getErrorMessage($status, $code, $message, $devMessage);  
    }
    
    /**
     * Get error message of account has been removed by organization
     * @date 12-Dec-2016
     * @author Chomnan Im <chomnan.im@workevolve.com>
     * @return json error message of account is already activated
     */
    public static function getAccountRemovedError($organization){
        $status             =   400;
        $code               =   400;
        $message            =   "This invitation has been removed by $organization and is no longer valid. Please contact $organization to receive a new invitation.";
        $devMessage         =   "This invitation has been removed by Organization.";
           
        return self::getErrorMessage($status, $code, $message, $devMessage);  
    }
    
    /**
     * Get error message of url param data error
     * @date 08-Dec-2016
     * @author Seng Sathya <sathya.seng@workevolve.com>
     * @return json error message 
     */
    public static function getUrlParamValueError($urlParam)
    {
        $status             =   400;
        $code               =   400;
        $message            =   "Incorrect ".$urlParam.' Value';
        $devMessage         =   "Incorrect ".$urlParam.' Value';
        
        return self::getErrorMessage($status, $code, $message, $devMessage);
    }
    
    /**
     * Get error message url params error
     * @date 21-Dec-2016
     * @author Chomnan Im <chomnan.im@workevolve.com>
     * @return json error message 
     */
    public static function getRequestUrlParamsError()
    {
        $status             =   400;
        $code               =   400;
        $message            =   'Incorrect url params.';
        $devMessage         =   'Incorrect url params.';
        
        return self::getErrorMessage($status, $code, $message, $devMessage);
    }
    /**
     * Token expired restful error message
     * @date 17-Mar-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @return json error message 
     */
    public static function getExpiredAccessTokenError()
    {
        $status             =   400;
        $code               =   2103;
        $message            =   'Your current session has been expired.';
        $devMessage         =   'Token Expired';
        
        return self::getErrorMessage($status, $code, $message, $devMessage);
    }
    /**
     * Get type of object library error message with version
     * @date 10-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @return json error message
     */
    public static function getObjectLibraryTypeError(){
        $status             =   400;
        $code               =   2105;
        $message            =   'You cannot set version for this type of object library';
        $devMessage         =   'Only object library with type: program and report that can be added version';
        
        return self::getErrorMessage($status, $code, $message, $devMessage);
    }
    /**
     * Get unique validation error message with object library, menu, sub menu
     * @date 28-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @return json error message
     */
    public static function getUniqueValidationError($ms){
        $status             =   400;
        $code               =   2106;
        $message            =   $ms;
        $devMessage         =   $ms;
        
        return self::getErrorMessage($status, $code, $message, $devMessage);
    }
    /**
     * Get voting error message
     * @date 26-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @return json error message
     */
    public static function getAlreadyVotingError(){
        $status             =   400;
        $code               =   2109;
        $message            =   "User already vote on this project";
        $devMessage         =   "Bad request user already voted on this project";
        
        return self::getErrorMessage($status, $code, $message, $devMessage);
    }
    
    public static function getInUsedError($resource){
        $status             =   400;
        $code               =   2110;
        $message            =   "This " . $resource . " is in used in other collections.";
        $devMessage         =   "This resource is in used in other collections or relationship.";
        
        return self::getErrorMessage($status, $code, $message, $devMessage);
    }
    
    public static function getPermissionError(){
        $status             =   400;
        $code               =   2111;
        $message            =   "You do not have permission to do this.";
        $devMessage         =   "You are not allowed to do this operation on this resource.";
        return self::getErrorMessage($status, $code, $message, $devMessage);
    }
}
