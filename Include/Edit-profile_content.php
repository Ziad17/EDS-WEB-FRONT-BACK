<?php

require_once '../Modules/Validation/'."PersonValidator.php";
require_once '../Modules/Database/PersonAction.php';
require_once '../Modules/FileManagement/'."FileRepoHandler.php";
require_once '../Modules/Database/FileAction.php';




error_reporting(E_ALL);




try {
    $personRef = Person::Builder()->setID(SessionManager::getID())->setEmail(SessionManager::getEmail())->build();
    $personAction = new PersonAction($personRef);
    $fileAction = new FileAction($personRef);
    $handler = new FileRepoHandler($fileAction);



    //fill Info
    $detailedPersonRef=$personAction->getMyDetails();
    $phoneNumber=$detailedPersonRef->getPhoneNumber();
    $bio=$detailedPersonRef->getBio();
    $phd=$detailedPersonRef->getPhd();
    $image=$detailedPersonRef->getImgRef();
    //TODO::populate the data

}

catch (Exception $e) {
    //FIXME::HANDLE ERRORS
    echo $e->getMessage();
    $FormErrors[] = $e->getMessage();
    // header("HTTP/1.1 503 Not Found");
    //exit(503);
}


if($_SERVER['REQUEST_METHOD']=='POST') {
    if ($_POST['save_info']=='save_info') {
        $phoneNumber=$_POST['phone_number'];
        $phd=$_POST['phd'];
        $bio=$_POST['bio'];
        $newPersonInfo=Person::Builder()
            ->setEmail($personRef->getEmail())
            ->setID($personRef->getID())
            ->setBio($bio)
            ->setPhd($phd)
            ->setPhoneNumber($phoneNumber)
            ->setImgRef($image)
            ->build();

        if($personAction->editMyInfo($newPersonInfo))
        {
            //TODO :: implement success message
            $message="Updated Successfully";
            echo $message;
        }
        else{
            //TODO :: implement failure message
            $error="Could Not Update Your Info";
            echo $error;
        }

    }
    else if ($_POST['save_password']=='save_password')
    {
        try {
            //TODO::IMPLEMENT SUCCESS AND FAIL
            $old = $_POST['old_pass'];
            $new_pass = $_POST['new_password'];
            $con_pass = $_POST['con_password'];
            if ($old == $new_pass) {
                echo 'New Password Cannot Be The Same As The Old Password';
            }
            if ($new_pass == $con_pass) {
                $error=$personAction->updateMyPassword($personRef->getEmail(), $old, $new_pass);
                if($error!='')
                {echo $error;}
                else{signOut(); }
            }

            else
            {
                echo 'Passwords Are Not Matched';
            }
        }
        catch (Exception $e){echo $e->getMessage();}

    }
    else if($_POST['change_photo']=='change_photo')
    {
        //TODO:: SUCCESS AND FAILING MESSAGE
        //CRITICAL ENABLE VALIDATION

        try {
            $file_name = strtolower($_FILES["fileToUpload"]["name"]);
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));;
            $file_size_in_bytes = $_FILES["fileToUpload"]["size"];
            $isImage = getimagesize($_FILES["fileToUpload"]["tmp_name"]);


            if ($isImage) {
               // $validator = new ImageValidator();
                $error = '';
            //    $error = $validator->validateExtension($file_ext);
            //    $error = $validator->validateSize($file_size_in_bytes);
            //    $error = $validator->validateName($file_name);
                if ($error == '') {
                    $content = file_get_contents($_FILES["fileToUpload"]["tmp_name"]);
                    if ($handler->createImage($file_name, $content)) {
                        $personAction->updateMyPhoto($file_name);
                        echo 'success';
                        echo "<meta http-equiv='refresh' content='0'>";

                    } else {
                        echo 'fail';
                    }
                }
                else{  echo $error;
                    }


            } else {
                echo 'its not an image';
            }
        }
        catch(Exception $e){echo $e->getMessage();}

    }
}

?>







<div align="center" class="col-md-4">
    <form action="" method="post" enctype="multipart/form-data">
  <div class="wrapper_pic" style="  background: url('<?php  if(isset($image) )
    {

        echo $handler->getImagePrivateURI($image);
    }
    else{echo '../img/undraw_male_avatar_323b.svg';}?>');
 "  >

    <span class="cam"><i class="fas fa-camera"></i></span>
    <input type="file" name="fileToUpload" class="my_file">
  </div>
        <button type="submit" name="change_photo" value="change_photo" class=" col-sm-12 btn btn-primary ">Save Photo</button>

    </form>
  <form class="form-style mt-5">
    <h2> List Of Actions Available </h2>
    <ol>
      <li> edit info </li>
      <li> wait to add more </li>
    </ol>
  </form>
</div>


<div align="center" class="col-md-8">
<form class="form-style" action="" method="POST" accept-charset="utf-8">
  <h1>General</h1>
 <!-- <div class="form-group row">
    <label for="F_name" class="col-sm-3 col-form-label">First Name</label>
    <div class="col-sm-9">
      <input class="form-control" id="F_name" type="text" name="first_name" placeholder="First Name" value="<?php /*if (isset($firstName)) {echo htmlspecialchars($firstName);}; */?>" required/>
    </div>
  </div>-->

 <!-- <div class="form-group row">
    <label for="M_name" class="col-sm-3 col-form-label">Middle Name</label>
    <div class="col-sm-9">
      <input class="form-control" id="M_name" type="text" name="second_name" placeholder="Middle Name" value="<?php /*if (isset($middleName)) {echo htmlspecialchars($middleName);};*/?>" required/>
    </div>
  </div>-->

<!--  <div class="form-group row">
    <label for="L_name" class="col-sm-3 col-form-label">Last Name</label>
    <div class="col-sm-9">
      <input class="form-control" id="L_name" type="text" name="last_name" placeholder="Last Name" value="<?php /*if (isset($lastName)) {echo htmlspecialchars($lastName);};*/?>" required/>
    </div>
  </div>-->

<!--  <div class="form-group row">
    <label for="email" class="col-sm-3 col-form-label">Email</label>
    <div class="col-sm-9">
      <input class="form-control" id="email" type="email" name="email" placeholder="Enter Your User Email" value="<?php /*if (isset($email)) {echo htmlspecialchars($email);};*/?>" required/>
    </div>
  </div>-->
  <div class="form-group row">
    <label for="Phone" class="col-sm-3 col-form-label">Phone</label>
    <div class="col-sm-9">
      <input class="form-control" id="Phone" type="tel" name="phone_number" placeholder="Enter Your Phone Number" value="<?php if (isset($phoneNumber)) {echo htmlspecialchars($phoneNumber);};?>" pattern="[0-9]{11}" required/>
    </div>
  </div>

 <!-- <div class="form-group row">
    <label for="Acc_num" class="col-sm-3 col-form-label">Academic Number</label>
    <div class="col-sm-9">
      <input class="form-control" id="Acc_num" type="number" name="acd_number" placeholder="Academic Number" value="<?php /*if (isset($acd_number)) {echo htmlspecialchars($acd_number);};*/?>" required/>
    </div>
  </div>-->

<!--  <div class="form-group row">
    <label for="Acc_num" class="col-sm-3 col-form-label">City</label>
    <div class="col-sm-9">
      <select name="City" id="inputCity" class="form-control" required>

      <?php
/*
      foreach ($cities as $city) {
      $city_name = ucfirst($city->getName());
      echo "<option value=" . htmlspecialchars($city->getShortcut()) . ">" . htmlspecialchars($city_name) . "</option>";
      }


      */?>
      </select>
  </div>
  </div>-->

<!--  <div class="form-group row">
    <label for="Acc_num" class="col-sm-3 col-form-label">Faculty</label>
    <div class="col-sm-9">
      <select name="faculty" id="inputfaculty" class="form-control" required>
      <?php
/*
      foreach ($faculties as $faculty) {

      $faculty_name = htmlspecialchars($faculty->getName());

      echo '<option value=' . '"' . $faculty->getName() . '"' . '>' . $faculty_name . '</option>';

      }

      */?>
      </select>
    </div>
  </div>-->
  <div class="form-group row mb-5">
    <label for="phd" class="col-sm-3 col-form-label">PHD Certificate</label>
      <div class="col-sm-9">
          <input class="form-control" id="phd" type="text" name="phd" placeholder="Enter Your PHD Certificate" value="<?php if (isset($phd)) {echo htmlspecialchars($phd);};?>" />
      </div>
  </div>

    <div class="form-group row mb-5">
        <label for="bio" class="col-sm-3 col-form-label">Profile Bio</label>
        <div class="col-sm-9">
            <input style="height: 100px;text-align: start" class="form-control" id="bio" type="text" name="bio" placeholder="Enter Your Profile Bio" value="<?php if (isset($bio)) {echo htmlspecialchars($bio);};?>" />
        </div>
    </div>

  <div class="form-group row">
    <div class="col-sm-12">
      <button type="submit" name="save_info"  value='save_info' class=" col-sm-12 btn btn-primary ">Save</button>
    </div>
  </div>
</form>



<form class="form-style" action="" id="save_password" method="POST" accept-charset="utf-8">
  <h1>Password</h1>
  <div class="form-group row">
    <label for="old_pass" class="col-sm-3 col-form-label">Old Password</label>
    <div class="col-sm-9">
      <input class="form-control" id="old_pass" type="password" name="old_pass" placeholder="Enter Your Old Password" value="" required/>
    </div>
  </div>
  <div class="form-group row">
    <label for="new_password" class="col-sm-3 col-form-label">New Password</label>
    <div class="col-sm-9">
      <input class="form-control" id="new_password" type="password" name="new_password" placeholder="Enter Your New Password" value="" required/>
    </div>
  </div>
  <div class="form-group row  mb-5">
    <label for="con_password" class="col-sm-3 col-form-label">Confirm  Password</label>
    <div class="col-sm-9">
      <input class="form-control" id="con_password" type="password" name="con_password" placeholder="Confirm Password" value="" required/>
    </div>
  </div>
  <div class="form-group row">
    <div class="col-sm-12">
      <button type="submit" name="save_password" value="save_password" class=" col-sm-12 btn btn-primary ">Save</button>
    </div>
  </div>
</form>
</div>
