<?php

class Category
{
    /**
     * @var PDO
     */
    private $db = null;
    private $user = null;
    //private $imageId = null;

    /**
     * @param $db
     * @param $user User
     */
    public function __construct($db, $user = null)
    {
        if ($user != null) {
            $this->user = $user->getUser();
        }
        $this->db = $db;
    }

    public function addCategory($categoryName, $firstUsername)
    {
        if ($this->checkCategory($categoryName)) {
            $createImage = $this->createImage($firstUsername);
            if ($createImage) {
                $createCategory = $this->createCategory($categoryName, $createImage);
                if ($createCategory) {
                    return array("error" => false);
                } else {
                    return array("error" => true, "reason" => "Error code 0x000452");
                }
            } else {
                return array("error" => true, "reason" => "Error code 0x000451");

            }
        } else {
            return array("error" => true, "reason" => "This category already created.");
        }
    }

    public function checkCategory($categoryName)
    {
        $sth = $this->db->prepare("select id from categories where name = ?");
        $sth->execute(array($categoryName));
        $sth->fetch(PDO::FETCH_ASSOC);
        $rowCount = $sth->rowCount();
        if ($rowCount <= 0) {
            return true;
        } else {
            return false;
        }
    }

    private function createCategory($categoryName, $imageId)
    {
        $sth = $this->db->prepare("INSERT INTO `categories` ( `adder`, `name`) VALUES ( :adderId, :category_name);");
        $sth->execute(array("adderId" => $this->user["id"], "category_name" => $categoryName));
        $sth->fetch();

        $categoryId = $this->db->lastInsertId();
        $sthCategoryData = $this->db->prepare("insert into categoryData (categoryId,imageId) values (:category_id, :imageId)");
        $sthCategoryData->execute(array("category_id" => $categoryId, "imageId" => $imageId));


        return true;
    }

    private function createImage($firstUsername)
    {
        $image = new Image($firstUsername);
        if ($image->getImage() != null) {
            if ($image->imageId != null) {
                return $image->imageId;
            } else {
                return false;
            }
        } else {
            return false;
        }

    }

    public function getCategoriesForIndex($page)
    {
        if ($page != 1) {
            $limitRow = $page * 8;
            $limit = $limitRow . ",8";
            sleep(2);
        } else {
            $limit = "0,8";
        }

        $sth = $this->db->prepare(" select distinct categoryId, SUM(count) as sumCount from categoryData group by categoryId order by sumCount desc limit " . $limit);
        $sth->execute();
        $fth = $sth->fetchAll(PDO::FETCH_ASSOC);
        $renderedData = "";
        $lastColor = "";
        foreach ($fth as $categoryData) {
            $categoryId = $categoryData["categoryId"];
            $categoryImages = $this->getCategoryImages($categoryId);
            $categoryName = $this->getCategoryNameWithCategoryId($categoryId);
            $categoryCard = new CategoryCard($categoryId, $categoryImages, $categoryName, $lastColor);
            $lastColor = $categoryCard->lastColor;
            $renderedData .= $categoryCard->render();
        }
        return $renderedData;

    }

    public function getUserCategories($page)
    {
        if ($page != 1) {
            $limitRow = $page * 8;
            $limit = $limitRow . ",8";
            sleep(2);
        } else {
            $limit = "0,8";
        }
        $user = new User($this->db);
        $user_id = $user->getUser()["id"];
        $categoryIds = $user->getUserAddedCategories($user_id);
        $categoryIdsArr = array();
        foreach ($categoryIds as $categoryId) {
            array_push($categoryIdsArr, $categoryId["id"]);
        }
        $categoryIdsStr = implode(",", $categoryIdsArr);
        $q = "select distinct categoryId, SUM(count) as sumCount from categoryData WHERE categoryId IN (" . $categoryIdsStr . ") group by categoryId order by sumCount desc limit " . $limit;
        $sth = $this->db->prepare($q);
        $sth->execute();
        $fth = $sth->fetchAll(PDO::FETCH_ASSOC);
        $renderedData = "";
        $lastColor = "";
        foreach ($fth as $categoryData) {
            $categoryId = $categoryData["categoryId"];
            $categoryImages = $this->getCategoryImages($categoryId);
            $categoryName = $this->getCategoryNameWithCategoryId($categoryId);
            $categoryCard = new CategoryCard($categoryId, $categoryImages, $categoryName, $lastColor);
            $lastColor = $categoryCard->lastColor;
            $renderedData .= $categoryCard->render();
        }
        return $renderedData;

    }

    private function getCategoryImages($categoryId)
    {
        $sth = $this->db->prepare("select * from categoryData where categoryId = :catid");
        $sth->execute(array("catid" => $categoryId));
        $fth = $sth->fetchAll(PDO::FETCH_ASSOC);
        $arr = array();
        foreach ($fth as $categoryData) {
            array_push($arr, encryptOrDecrypt($this->getImageWithImageId($categoryData["imageId"])));
        }
        return $arr;
    }

    public function getCategoryNameWithCategoryId($categoryId)
    {
        $sth = $this->db->prepare("select name from categories where id = :catid");
        $sth->execute(array("catid" => $categoryId));
        return $sth->fetch(PDO::FETCH_ASSOC)["name"];
    }

    public function addImageToCategory($categoryId, $imageId)
    {
        $sth = $this->db->prepare("insert into categoryData (categoryId,imageId) values (?,?)");
        $sth->execute(array(
            $categoryId,
            $imageId
        ));
        $sth->fetch();
        return true;
    }

    private function getImageWithImageId($imageId)
    {
        $sth = $this->db->prepare("select username from images where id = :id");
        $sth->execute(array("id" => $imageId));
        return $sth->fetch(PDO::FETCH_ASSOC)["username"];
    }

    public function getAllImagesWithCategoryId($categoryId, $ignoreVoters = true)
    {
        if ($ignoreVoters) {
            $this->user = new User($this->db);
            $this->user->getUser();
        }
        $sth = $this->db->prepare("select id,imageId,voters from categoryData where categoryId = ? order by count desc");
        $sth->execute(array($categoryId));
        $fth = $sth->fetchAll(PDO::FETCH_ASSOC);
        $arr = array();
        $categoryName = $this->getCategoryNameWithCategoryId($categoryId);

        foreach ($fth as $categoryData) {
            if (count($arr) == 2 && $ignoreVoters)
                return $arr;
            if ($ignoreVoters) {
                $voters = $categoryData["voters"];
                $explodedVoters = explode(",", $voters);
                $isBreak = false;
                foreach ($explodedVoters as $explodedVoter) {
                    if ($explodedVoter == $this->user->user["id"]) {
                        $isBreak = true;
                    }
                }
                if ($isBreak) {
                    continue;
                }
            }
            $sthImage = $this->db->prepare("select username from images where id = ?");
            $sthImage->execute(array($categoryData["imageId"]));
            $fthImage = $sthImage->fetch(PDO::FETCH_ASSOC);
            $encryptData = encryptOrDecrypt($fthImage["username"]);
            $arr[] = array("image" => $encryptData, "name" => $fthImage["username"], "categoryName" => $categoryName, "categoryId" => encryptOrDecrypt($categoryData["id"]));

        }
        return $arr;

    }


}