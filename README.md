 # twyple-sdk-php

 ### Installation
 ```composer require twyple/twyple-sdk-php```

 ### Getting started
 Create an endpoint and initialize the package. All you have to do is read the input data and call the Handler.

 #### Cashfree
 ```http://your/server/route.php```

 ```
 <?php
    require 'vendor/autoload.php';
    use Twyple\Cashfree\Payments;
    $inputJSON = json_decode(file_get_contents('php://input'), true); //$inputJSON = $_POST;

    //cashfree credentials. You can get it from merchant dashboard
    $appId = "cashfreeAppID";
    $secretKey = "cashfreeAppSecret";

    $cashfree = new Payments($appId, $secretKey);

    $handlerRes = $cashfree->Handler($inputJSON);
    echo json_encode($handlerRes);

 ?>

 ```
