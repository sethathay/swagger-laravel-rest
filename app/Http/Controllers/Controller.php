<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
// app/Http/Controllers/Controller.php
/**
 * @SWG\Swagger(
 *   host="localhost:81",
 *   schemes={"http"},
 *   basePath="/l5-swaggers/public/v1",
 *   @SWG\Info(
 *     title="workEvolve API",
 *     description="workEvolve Restful API generated by Swagger",
 *     version="0.1.0",
 *     @SWG\Contact(  
 *       email="development@workevolve.com"  
 *     ),
 *     @SWG\License(  
 *       name="workEvolve License @2018",  
 *       url="http://www.workevolve.com/api/license"  
 *     )
 *   ),
 *  @SWG\Tag(
 *      name="Project",
 *      description="CRUD Operation of restful resource PROJECT",
 *      @SWG\ExternalDocumentation(
 *      description="More documentation of resource PROJECT",
 *      url="http://swagger.io"
 *     )
 *   ),
 *  @SWG\Tag(
 *      name="AddressBook",
 *      description="CRUD Operation of restful resource ADDRESS BOOK",
 *      @SWG\ExternalDocumentation(
 *      description="More documentation of resource ADDRESS BOOK",
 *      url="http://swagger.io"
 *   )
 *  ),
 *  @SWG\Tag(
 *      name="Sample",
 *      description="Sample API"
 *  ),
 *  @SWG\SecurityScheme(
 *     securityDefinition="default",
 *     type="apiKey",
 *     in="header",
 *     name="Authorization"
 *   )
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
