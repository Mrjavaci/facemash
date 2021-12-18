<?php
include __DIR__ . "/vendor/autoload.php";
include __DIR__ . "/php/include.php";
$whoops = new \Whoops\Run;
$whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
$whoops->register();
$operation = $_POST["operation"];

switch ($operation) {
    case "login":
        $database = new DataBase();
        $user = new User($database->getDb());
        if ($user->checkLoginWithCredentials($_POST["email"], $_POST["pass"], 0)) {
            jsonDie(array(
                "login" => true,
                "token" => $user->getToken()
            ));
        } else {
            jsonDie(array("login" => false));
        }
        break;
    case "register":
        $database = new DataBase();
        $user = new User($database->getDb());
        $verify = $user->verifyCredentials($_POST, 0);
        if (!$verify["error"] && filter_var($_POST["email"], FILTER_VALIDATE_EMAIL) && preg_match('/^[a-zA-Z0-9._].{1,30}+$/', $_POST["username"]) && preg_match('/^.{5,31}$/', $_POST["pass"])) {
            jsonDie($database->addUser($_POST));
        }
        jsonDie($verify);
        break;
    case "reset":
        $database = new DataBase();
        $user = new User($database->getDb());
        $verify = $user->verifyCredentials($_POST, 1);
        if ($verify["error"] && filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
            jsonDie($database->addPassKey($_POST));
            // Sıfırlama maili gönder.
            // myDie(array("reset" => true, "reason" => "Mail sended"));

        }
        jsonDie(array("reset" => false, "reason" => "Mail undefined"));
        break;
    case "changePass":
        $database = new DataBase();
        $user = new User($database->getDb());
        if (preg_match('/^.{5,31}$/', $_POST["pass"])) {
            jsonDie($database->updatePass($_POST));
        }
        jsonDie(array("changePass" => false, "reason" => "password problem"));
        break;
    case "addCategory":
        $database = new DataBase();
        $user = new User($database->getDb());
        $category = new Category($database->getDb(), $user);
        if ($user->checkLoginWithToken() && preg_match('/^.{2,31}$/', $_POST["categoryName"]) && preg_match('/^[a-zA-Z0-9._].{1,30}+$/', $_POST["firstUsername"]) ) {
            jsonDie($category->addCategory($_POST["categoryName"], $_POST["firstUsername"]));
        } else {
            jsonDie(array("error" => true, "reason" => "Please Login."));
        }
        break;
    case "addPhoto":
        $database = new DataBase();
        $user = new User($database->getDb());
        if ($user->checkLoginWithToken()) {
            if (isset($_POST["userName"]) && !empty($_POST["userName"])) {
                $image = new Image($_POST["userName"]);
                if ($image->getImage()) {
                    $category = new Category($database->db, $user);
                    $categoryName = $category->getCategoryNameWithCategoryId($_POST["categoryId"]);
                    if (!empty($categoryName) && $categoryName) {
                        if ($category->addImageToCategory($_POST["categoryId"], $image->imageId)) {
                            jsonDie(array("error" => false));
                        }
                    }
                    jsonDie(array("error" => false));
                } else {
                    jsonDie(array("error" => true, "reason" => "0x000454"));
                }

            }
        } else {
            jsonDie(array("error" => true, "reason" => "Please Login."));
        }
        break;
    case "getCategory":
        $database = new DataBase();
        $category = new Category($database->getDb());;
        htmlDie($category->getCategoriesForIndex($_POST["page"]));
        break;
    default:
        die(json_encode(array("error" => true, "reason" => "unexpected operation")));
}
