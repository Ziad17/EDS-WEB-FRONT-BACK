<?php

require_once "Action.php";


class PersonAction extends Action
{
    private PersonPermissions $myPersonPermissions;
    private bool $active;

    /**
     * PersonAction constructor.
     * @param Person $myPersonRef
     * @throws PermissionsCriticalFail
     */
    public function __construct(Person $myPersonRef)
    {
        parent::setConnection($this);
        $this->myPersonRef = $myPersonRef;
        $this->updateMyPersonsPermissionsSum();

    }


    public function updateMyPersonsPermissionsSum()
    {
        //updates the permissions array
        $conn = $this->getDatabaseConnection();
        $sql = "SELECT persons_permissions_sum,active FROM PersonRolesAndPermissions_view WHERE contact_email=?";
        $params = array($this->myPersonRef->getEmail());
        $stmt = $this->getParameterizedStatement($sql, $conn, $params);
        if ($stmt == false) {

            //removeOnProduction
            //$error="Could not get details of the person roles";
            $error = sqlsrv_errors()[0]['message'];
            $this->closeConnection($conn);
            throw new SQLStatmentException($error);
        }
        if (sqlsrv_has_rows($stmt)) {
            //FIXME: add logic if a person has more than one role
            $row = sqlsrv_fetch_object($stmt);
            $active = (bool)$row->active;
            if ($active == false) //check for active status
            {
                throw new PersonOrDeactivated("Your role has been deactivated by an admin");
            } else {

                $sum = (int)$row->persons_permissions_sum;

                if ($sum > 0) {
                    $this->closeConnection($conn);
                    $this->myPersonPermissions = new PersonPermissions($sum);

                } else {
                    $this->closeConnection($conn);
                    throw new NoPermissionsGrantedException('Role Found But No Permissions Is Granted');
                }
            }
        } else {
            $this->closeConnection($conn);
            throw new PersonHasNoRolesException('No Roles Found');
        }
    }

    /*    public function getPersonData(string $email): Person
        {

            //TODO: ADD PersonActionLogs
            //REQUIRED_PERMISSION=VIEW_PERSON_PROFILE=0
            if ($this->myPersonPermissions->getPermissionsFromBitArray($this->myPersonPermissions->VIEW_PERSON_PROFILE)) {
                $conn = $this->getDatabaseConnection();
                $sql = "SELECT * FROM Person_view WHERE contact_email=?";
                $params = array($email);
                $stmt = $this->getParameterizedStatement($sql, $conn, $params);
                if ($stmt == false || !sqlsrv_has_rows($stmt)) {
                    $this->closeConnection($conn);
                    throw new SQLStatmentException("Error fetching the required data");
                }
                $rows = sqlsrv_fetch_array($stmt);
                $fName = $rows[0][0];
                $mName = $rows[0][1];
                $lName = $rows[0][2];
                $_email = $rows[0][3];
                $gender = $rows[0][4];
                $city = $rows[0][5];
                $phone_number = $rows[0][6];
                $phd = $rows[0][7];
                $bio = $rows[0][8];

                $this->closeConnection($conn);
                return Person::Builder()->setFirstName($fName)
                    ->setMiddleName($mName)
                    ->setLastName($lName)
                    ->setEmail($_email)
                    ->setGender($gender)
                    ->setCity($city)
                    ->setPhoneNumber($phone_number)
                    ->setPhd($phd)
                    ->setBio($bio)
                    ->build();

            } else {
                throw new NoPermissionsGrantedException("User does not have the permissions required for this process");
            }
        }

        public function getPersonFiles(string $email): array //of files
        {

            return array();
        }

        public function activatePerson(string $targetEmail): bool
        {
            if ($this->getPersonRoleInstitution($targetEmail) == $this->getPersonRoleInstitution($this->myPersonRef->getEmail())) {
                return $this->activatePersonWithinInstitution($targetEmail);
            } else {
                return $this->activatePersonOutsideInstitution($targetEmail);
            }
        }

        public function deactivatePerson(string $targetEmail): bool
        {
            if ($this->getPersonRoleInstitution($targetEmail) == $this->getPersonRoleInstitution($this->myPersonRef->getEmail())) {
                return $this->deactivatePersonWithinInstitution($targetEmail);
            } else {
                return $this->deactivatePersonOutsideInstitution($targetEmail);
            }
        }

        private function activatePersonWithinInstitution(string $targetEmail): bool
        {
            //REQUIRED_PERMISSION=$ACTIVATE_PERSON_WITHIN_INSTITUTION=5
            if ($this->myPersonPermissions->getPermissionsFromBitArray($this->myPersonPermissions->CREATE_PERSON_WITHIN_INSTITUTION)) {
                if ($this->compareRoleLevel($this->myPersonRef->getEmail(), $targetEmail)) {
                    $permission = $this->myPersonPermissions->CREATE_PERSON_WITHIN_INSTITUTION;
                    return $this->setRoleActiveStatus(true, $targetEmail, 2 ** $permission);
                } else {
                    throw new LowRoleForSuchActionException("The targeted person has a higher role level");
                }
            } else {
                throw new NoPermissionsGrantedException("User does not have the permissions required for this process");
            }
        }*/

    /*   private function setRoleActiveStatus(bool $active, string $targetEmail, int $permission): bool
       {

           $conn = $this->getDatabaseConnection();
           sqlsrv_begin_transaction($conn);
           $sql = "UPDATE PersonRolesAndPermissions_view SET active=? WHERE email=?";
           $params = array($active, $targetEmail);
           $stmt = $this->getParameterizedStatement($sql, $conn, $params);
           $logSQL = "INSERT INTO PersonActionLogs(affecter_person_id,affected_person_id,action_date,permission_action_performed) VALUES(?,?,GETDATE(),?);";
           $logParams = array($this->myPersonRef->getID(), $this->getIdFromPersonEmail($targetEmail), $permission);
           $logsStmt = $this->getParameterizedStatement($logSQL, $conn, $logParams);
           if ($stmt == false || sqlsrv_rows_affected($stmt) == false || $logsStmt == false) {
               sqlsrv_rollback($conn);
               $this->closeConnection($conn);
               return false;
           } else {
               sqlsrv_commit($conn);
               $this->closeConnection($conn);
               return true;
           }
       }*/
    public function canDeactivatePerson(): bool
    {
        try {
            return $this->myPersonPermissions->getPermissionsFromBitArray($this->myPersonPermissions->DEACTIVATE_PERSON_WITHIN_INSTITUTION);
        } catch (Exception $e) {
            throw new  NoPermissionsGrantedException('Person Deactivation Permissions Is Not Granted');
        }
    }

    private function deactivatePersonWithinInstitution(string $targetEmail): bool
    {

        //FIXME:: THIS LOGIC IN UNSTABLE AND NEEDS TO BE REVIEWED
        if ($this->canDeactivatePerson() && $this->isEmployeeOfInstitution($this->getBaseFacultyOfPerson($targetEmail))) {
            $myRole = $this->getRolesOfPerson($this->myPersonRef->getEmail())[0];
            $personRole = $this->getRolesOfPerson($this->myPersonRef->getEmail())[0];
            if ($this->compareRoleLevel($this->myPersonRef->getEmail(), $targetEmail)) {
                $permission = $this->myPersonPermissions->DEACTIVATE_PERSON_WITHIN_INSTITUTION;
                return $this->setRoleActiveStatus(FALSE, $targetEmail, 2 ** $permission);
            } else {
                throw new LowRoleForSuchActionException("The targeted person has a higher role level");
            }

        } else {
            throw new NoPermissionsGrantedException("User does not have the permissions required for this process");
        }
    }


    public function canCreatePerson(): bool
    {
        try {
            return $this->myPersonPermissions->getPermissionsFromBitArray($this->myPersonPermissions->CREATE_PERSON_WITHIN_INSTITUTION);
        } catch (Exception $e) {
            throw new  NoPermissionsGrantedException('Institution Creation Permissions Is Not Granted');
        }
    }

    public function getRoleCountOfAPerson(string $email): int
    {
        $conn = $this->getDatabaseConnection();
        $sql = "SELECT COUNT(ID) FROM Employees WHERE person_id=?";
        $params = array($this->getIdFromPersonEmail($email));
        $stmt = $this->getParameterizedStatement($sql, $conn, $params);
        if ($stmt == false) {
            //removeOnProduction
            //$error="Could not get the roles of this person";
            $error = sqlsrv_errors()[0]['message'];
            $this->closeConnection($conn);
            throw new PersonHasNoRolesException($error);

        }
        $row = sqlsrv_fetch_array($stmt)[0];
        $count = (int)$row[0];
        $this->closeConnection($conn);
        return $count;

    }

    public function getMyRoles(int $id): array
    {
        $conn = $this->getDatabaseConnection();
        $sql = "SELECT role_id,	role_front_name,role_priority_lvl,institution_name FROM [dbo].[PersonRolesAndPermissions_view] WHERE active=1 AND person_id=?";
        $params = array($id);
        $stmt = $this->getParameterizedStatement($sql, $conn, $params);
        if ($stmt == false) {

            //removeOnProduction
            //$error="Could not get the roles of this person";
            $error = sqlsrv_errors()[0]['message'];
            $this->closeConnection($conn);
            throw new PersonHasNoRolesException($error);

        }
        $roles = array();
        while ($row = sqlsrv_fetch_object($stmt)) {
            $roles[] = PersonRole::Builder()
                ->setPriorityLevel((int)$row->role_priority_lvl)
                ->setInstitutionName((string)$row->institution_name)
                ->setJobTitle((string)$row->role_front_name)
                ->setID((string)$row->role_id)
                ->build();

        }
        return $roles;
    }

    public function updateMyPhoto(string $photoNameOrUrl)
    {
        $conn=$this->getDatabaseConnection();
        $sql='UPDATE PersonContacts SET image_ref=? WHERE email=?';
        $params=array($photoNameOrUrl,$this->myPersonRef->getEmail());
        $stmt=$this->getParameterizedStatement($sql,$conn,$params);
        if ($stmt == false) {

            //removeOnProduction
            //$error="Could not update the photo of this person";
            $error = sqlsrv_errors()[0]['message'];
            $this->closeConnection($conn);
            throw new InsertionError($error);
        }
        $this->closeConnection($conn);
        return true;
    }

    public function editMyInfo(Person $newRef): bool
    {

        //TODO::CHECK IF THE IMAGE IS UPDATED OR NOT
        //TODO::Complete the logic depending on whether the user can change their names,emails,academicNumbers
        $conn = $this->getDatabaseConnection();
        sqlsrv_begin_transaction($conn);
        $contactsSql = "UPDATE PersonContacts SET phone_number=?,phd_certificate=N?,image_ref=?,bio=N? WHERE email=?;";
        $contactsParams = array($newRef->getPhoneNumber(), $newRef->getPhd(), $newRef->getImgRef(), $newRef->getBio(), $newRef->getEmail());
        $contactsStmt = $this->getParameterizedStatement($contactsSql, $conn, $contactsParams);
        if ($contactsStmt == false) {
            //removeOnProduction
            //$error="Could not Update PersonContact Info";
            $error = sqlsrv_errors()[0]['message'];
            sqlsrv_rollback($conn);
            $this->closeConnection($conn);
            throw new InsertionError($error);
        }
        //TODO CHECK IF OTHER INFO IN THE PERSON TABLE CAN BE CHANGED
        sqlsrv_commit($conn);
        $this->closeConnection($conn);

        return true;


    }

    public function updateMyPassword(string $email, string $oldPassword, string $newPassword): string
    {

        if(strlen($newPassword) < 8 ){
            return 'Password Must Be At Least 8 Characters long';
        }
        $conn = $this->getDatabaseConnection();
        $sql1 = "SELECT user_password FROM Person WHERE contact_email=? AND ID=?";
        $params1 = array($email, $this->myPersonRef->getID());
        $stmt1 = $this->getParameterizedStatement($sql1, $conn, $params1);
        if ($stmt1 == false) {
            //removeOnProduction
            //$error="Could not get the password of this person";
            $error = sqlsrv_errors()[0]['message'];
            $this->closeConnection($conn);
            throw new DataNotFound($error);
        }
        $row1 = sqlsrv_fetch_object($stmt1);
        $actualPassword = $row1->user_password;
        $actualPassword=EncryptionManager::Decrypt($actualPassword);
        if ($oldPassword == $actualPassword) {
            $sql2 = "UPDATE Person SET user_password=N? WHERE contact_email=? AND ID=?";
            $params2 = array(EncryptionManager::Encrypt($newPassword), $email, $this->myPersonRef->getID());
            $stmt2 = $this->getParameterizedStatement($sql2, $conn, $params2);
            if ($stmt2 == false) {

                //removeOnProduction
                //$error="Could not update the password of this person";
                $error = sqlsrv_errors()[0]['message'];
                $this->closeConnection($conn);
                throw new InsertionError($error);

            }
            $this->closeConnection($conn);
            return '';
        } else {
            $this->closeConnection($conn);
            return 'Wrong Old Password';
        }

    }

    public function getPersonPublicInfo(string $email): Person
    {
        $conn = $this->getDatabaseConnection();
        $sql = "SELECT * FROM PersonsHierarchy_view WHERE contact_email=?;";
        $params = array($email);
        $stmt = $this->getParameterizedStatement($sql, $conn, $params);
        if ($stmt == false) {
            $error = sqlsrv_errors()[0]['message'];
            $this->closeConnection($conn);
            throw new SQLStatmentException($error);
        }
        if (!sqlsrv_has_rows($stmt)) {
            $this->closeConnection($conn);
            throw new DataNotFound("Info Not Found");
        }
        $row = sqlsrv_fetch_object($stmt);
        return Person::Builder()
            ->setFirstName($row->first_name)
            ->setMiddleName($row->middle_name)
            ->setLastName($row->last_name)
            ->setGender($row->gender)
            ->setCity($row->city_name)
            ->setPhoneNumber($row->phone_number)
            ->setPhd($row->phd_certificate)
            ->setBio($row->bio)
            ->setImgRef($row->image_ref)
            ->setInstitution($row->base_faculty)
            ->setRoles($this->getMyRoles($this->myPersonRef->getID()))
            ->build();


    }

    public function getMyDetails(): Person
    {
        $conn = $this->getDatabaseConnection();
        $sql = "SELECT * FROM PersonsHierarchy_view WHERE ID=?;";
        $params = array($this->myPersonRef->getID());
        $stmt = $this->getParameterizedStatement($sql, $conn, $params);
        if ($stmt == false) {
            $error = sqlsrv_errors()[0]['message'];
            $this->closeConnection($conn);
            throw new SQLStatmentException($error);
        }
        if (!sqlsrv_has_rows($stmt)) {
            $this->closeConnection($conn);
            throw new DataNotFound("Info Not Found");
        }
        $row = sqlsrv_fetch_object($stmt);
        return Person::Builder()
            ->setID($this->myPersonRef->getID())
            ->setFirstName($row->first_name)
            ->setMiddleName($row->middle_name)
            ->setLastName($row->last_name)
            ->setEmail($row->contact_email)
            ->setGender($row->gender)
            ->setCity($row->city_name)
            ->setPhoneNumber($row->phone_number)
            ->setPhd($row->phd_certificate)
            ->setBio($row->bio)
            ->setImgRef($row->image_ref)
            ->setInstitution($row->base_faculty)
            ->setRoles($this->getMyRoles($this->myPersonRef->getID()))
            ->build();


    }

    public function createPerson(Person $person, string &$password, string $date, PersonRole $role): bool
    {

        /*
                  * Steps
                  * check if the user has the role to create within institution
         * check if the user is an admin in this institution
                  * upload personContacts record
                  * upload Person record
                  * create the employee
                  * inject the log
                  * */


        $myRole = $this->getMyRoles($this->myPersonRef->getID())[0];

        /*
                if ($this->getRoleCountOfAPerson($this->myPersonRef->getEmail())==1) {
                    //FIXME::ADD CODE HERE TO MAKE THE USER SELECT THE ROLE TO PERFORM THE ACTION
                } else {
                    $myRole = $this->getMyRoles($this->myPersonRef->getID())[0];
                }
        */


        if (!$this->compareRoleLevel($myRole->getRoleName(), $role->getID())) {
            throw new CannotCreateHigherEmployeeException("User Cannot Create Higher Roles");

        }
        if ($this->canCreatePerson() && $this->isEmployeeOfInstitution($role->getInstitutionName())) {

            $permission_value = 2 ** ($this->myPersonPermissions->CREATE_PERSON_WITHIN_INSTITUTION);

            //check if email exists
            if ($this->isUserExists($person->getEmail())) {
                $this->closeConnection($conn);

                throw new DuplicateDataEntry("The Email or AcademicNumber already exists");
            }
            $conn = $this->getDatabaseConnection();
            sqlsrv_begin_transaction($conn);

            //PersonContacts
            $sql1 = "INSERT INTO PersonContacts(email,phone_number,base_faculty) VALUES(?,?,?)";
            $params1 = array("{$person->getEmail()}",
                "{$person->getPhoneNumber()}",
                "{$this->getInstitutionNameByID((int)$person->getInstitution())}");
            $stmt1 = $this->getParameterizedStatement($sql1, $conn, $params1);

            if ($stmt1 == false) {
                //removeOnProduction
                //$error="Could not Insert PersonContacts";
                $error = sqlsrv_errors()[0]['message'];
                sqlsrv_rollback($conn);
                $this->closeConnection($conn);
                throw new InsertionError($error);


            }

            $sql2 = "SET NOCOUNT ON; INSERT INTO Person(first_name,
                   middle_name,
                   last_name,
                   user_password,
                   contact_email,
                   academic_number,
                   gender,
                   city_shortcut) VALUES(N?,N?,N?,N?,?,?,?,?); SELECT SCOPE_IDENTITY()";
            $params2 = array("{$person->getFirstName()}",
                "{$person->getMiddleName()}",
                "{$person->getLastName()}",
                "{$password}",
                "{$person->getEmail()}",
                "{$person->getAcademicNumber()}",
                "{$person->getGender()[0]}",//the first char of gender M or F
                "{$person->getCity()}");


            $stmt2 = $this->getParameterizedStatement($sql2, $conn, $params2);

            if ($stmt2 == false) {
                sqlsrv_rollback($conn);
                $this->closeConnection($conn);
                throw new InsertionError("Could not Insert Person");

            }


            //required info
            $row = sqlsrv_fetch_array($stmt2);
            $createdID = (int)$row[0];


            $sql3 = "INSERT INTO Employees(person_id,
                     role_id,
                      institution_id,
                      hiring_date,
                      employee_job_desc,
                      active) VALUES (?,?,?,?,N?,1)";
            $params3 = array($createdID,
                $role->getID(),
                $this->getInstitutionIDByName($role->getInstitutionName()),
                $date,
                $role->getJobDesc());
            $stmt3 = $this->getParameterizedStatement($sql3, $conn, $params3);
            if ($stmt3 == false) {
                sqlsrv_rollback($conn);
                $this->closeConnection($conn);
                throw new InsertionError("Could not Insert Employee");

            }


            if ($this->injectLog($permission_value, $this->myPersonRef->getID(), $createdID, $conn) == false) {
                sqlsrv_rollback($conn);
                //removeOnProduction
                //$error="Couldn't execute this statement";
                $error = (string)sqlsrv_errors()[0]['message'];
                $this->closeConnection($conn);
                throw new SQLStatmentException($error);
            } else {
                sqlsrv_commit($conn);
                $this->closeConnection($conn);
                return true;
            }


        } else {
            throw new NoPermissionsGrantedException("User does not have the permissions required for this process");
        }

    }

    private function injectLog(int $actionPerformed, int $creatorId, int $personId, &$conn): bool
    {
        $logSQL = "INSERT INTO PersonActionLogs(conductor_person_id,affected_person_id,action_date,permission_action_performed) VALUES(?,?,GETDATE(),?);";
        $logParams = array($creatorId, $personId, $actionPerformed);
        $logsStmt = $this->getParameterizedStatement($logSQL, $conn, $logParams);
        if ($logsStmt == false) {

            $this->closeConnection($conn);
            return false;
        }
        return true;
    }

    private function compareRoleLevel(int $myRoleID, int $toCreateRoleID): bool
    {
        //the first has to always be < the target because 1 is the max as in a tree indexing
        $makerRoleLevel = $this->getRoleDetails($myRoleID)->getPriorityLevel();
        $targetRoleLevel = $this->getRoleDetails($toCreateRoleID)->getPriorityLevel();
        if ($makerRoleLevel < $targetRoleLevel) {
            return true;
        }
        return false;
    }

    private function getRoleDetails(int $role_id): PersonRole
    {
        $conn = $this->getDatabaseConnection();
        $sql = "SELECT * FROM Roles WHERE ID=?";
        $params = array($role_id);
        $stmt = $this->getParameterizedStatement($sql, $conn, $params);
        if ($stmt == false || !sqlsrv_has_rows($stmt)) {

            $this->closeConnection($conn);
            throw new PersonHasNoRolesException("Could not get details of the person roles");
        }
        $row = sqlsrv_fetch_object($stmt);

        $personRole = PersonRole::Builder()->setPriorityLevel((int)$row->role_priority_lvl)
            ->setInstitutionName((string)$row->institution_name)
            ->setJobTitle((string)$row->role_front_name)
            ->setID((int)$row->role_id)
            ->build();

        $this->closeConnection($conn);
        return $personRole;
    }


    /*
        public function getAllPersonsHierarchy(): array //of persons nested array
        {
            //TODO: ADD PersonActionLogs
            //REQUIRED_PERMISSION=VIEW_ALL_PERSONS_HIERARCHY=2
            if ($this->myPersonPermissions->getPermissionsFromBitArray($this->myPersonPermissions->VIEW_ALL_PERSONS_HIERARCHY)) {
                $sql = "SELECT * FROM PersonsHierarchy_view WHERE gender=? OR gender=? GROUP BY priority_lvl ASC ";
                $params = array('M', 'F');
                return $this->structureBuilder($sql, $params);

            } else {
                throw new NoPermissionsGrantedException("User does not have the permissions required for this process");
            }

        }




        private function structureBuilder($sql, $params): array //of persons nested array
        {
            //TODO: ADD PersonActionLogs
            $conn = $this->getDatabaseConnection();
            $stmt = $this->getParameterizedStatement($sql, $conn, $params);
            if ($stmt == false || !sqlsrv_has_rows($stmt)) {
                $this->closeConnection($conn);
                throw new SQLStatmentException("Error fetching the required data");
            }
            $hierarchyArray = array();
            $singleArray = array();
            $current_priority = 1;
            while ($row = sqlsrv_fetch($stmt)) {
                $fName = $row[0];
                $mName = $row[1];
                $lName = $row[2];
                $_email = $row[3];
                $gender = $row[4];
                $city = $row[5];
                $phone_number = $row[6];
                $phd = $row[7];
                $bio = $row[8];
                $empTitle = $row[9];
                $priority_lvl = $row[10];
                $job_description = $row[11];
                if ($priority_lvl < $current_priority) {
                    $this->closeConnection($conn);
                    throw new SQLStatmentException("Bad arrangement of data");
                }
                $personRole = new PersonRole($empTitle, $priority_lvl, $job_description);
                $person = Person::Builder()
                    ->setFirstName($fName)
                    ->setMiddleName($mName)
                    ->setLastName($lName)
                    ->setEmail($_email)
                    ->setGender($gender)
                    ->setCity($city)
                    ->setPhd($phd)
                    ->setBio($bio)
                    ->setRoles(array($personRole))
                    ->build();
                if ($priority_lvl == $current_priority) {
                    $singleArray[] = $person;
                } else if ($priority_lvl > $current_priority) {
                    $hierarchyArray[] = $singleArray;
                    $current_priority = $priority_lvl;
                    $singleArray = array();
                    $singleArray[] = $person;
                }
            }
            $this->closeConnection($conn);
            return $hierarchyArray;


        }

        public function getAllPersonInInstitution(int $institutionID): array //of persons
        {
            //TODO: ADD PersonActionLogs
            //REQUIRED_PERMISSION=VIEW_ALL_PERSONS_IN_INSTITUTION= 1
            if ($this->myPersonPermissions->getPermissionsFromBitArray($this->myPersonPermissions->VIEW_ALL_PERSONS_IN_INSTITUTION)) {
                $sql = "SELECT * FROM PersonsHierarchy_view  WHERE gender=? OR gender=? AND institution_id=? GROUP BY priority_lvl ASC";
                $params = array('M', 'F', $institutionID);
                return $this->structureBuilder($sql, $params);

            } else {
                throw new NoPermissionsGrantedException("User does not have the permissions required for this process");
            }

        }*/


    private function getPersonRoleInstitution(string $targetEmail): int //the id of the institution
    {
        $conn = $this->getDatabaseConnection();
        $sql = "SELECT institution_id FROM PersonsHierarchy_view WHERE contact_email=?";
        $params = array($targetEmail);
        $stmt = $this->getParameterizedStatement($sql, $conn, $params);
        if ($stmt == false || !sqlsrv_has_rows($stmt)) {
            $this->closeConnection($conn);
            throw new PersonHasNoRolesException("Could not get details of the person roles");
        }
        $id = sqlsrv_fetch_array($stmt)[0][0];
        $this->closeConnection($conn);
        return $id;
    }


    //TODO::REFACTORING either leave these methods here or move them in the search module
    //normal search action

    //1-search for people with name,email or academic number
    public function searchForPeopleByNameOrEmail(string $emailOrName): array
    {
        $stripped = str_replace(' ', '', $emailOrName);
        $conn = $this->getDatabaseConnection();
        $sql = "SELECT image_ref,first_name,middle_name,last_name FROM Person_view WHERE CONCAT(first_name,middle_name,last_name) LIKE ? OR contact_email=?;";
        $sign = "%";
        $params = array($sign . $stripped . $sign, $stripped);
        $stmt = $this->getParameterizedStatement($sql, $conn, $params);
        if ($stmt == false) {
            $error = sqlsrv_errors()[0]['message'];
            $this->closeConnection($conn);
            throw  new SQLStatmentException($error);
        }
        $personsArr = array();
        while ($row = sqlsrv_fetch_object($stmt)) {
            $personsArr[] = Person::Builder()
                ->setFirstName($row->first_name)
                ->setMiddleName($row->middle_name)
                ->setLastName($row->last_name)
                ->setImgRef($row->image_ref)
                ->build();
        }
        return $personsArr;

    }

    public function searchForPeopleByAcademicOrPhone(string $phoneOrAcaNumber): array
    {
        $conn = $this->getDatabaseConnection();
        $sql = "SELECT image_ref,first_name,middle_name,last_name FROM Person_view WHERE phone_number=? OR academic_number=?;";
        $params = array($phoneOrAcaNumber, $phoneOrAcaNumber);
        $stmt = $this->getParameterizedStatement($sql, $conn, $params);
        if ($stmt == false) {
            $error = sqlsrv_errors()[0]['message'];
            $this->closeConnection($conn);
            throw  new SQLStatmentException($error);
        }
        $personsArr = array();
        while ($row = sqlsrv_fetch_object($stmt)) {
            $personsArr[] = Person::Builder()
                ->setFirstName($row->first_name)
                ->setMiddleName($row->middle_name)
                ->setLastName($row->last_name)
                ->setImgRef($row->image_ref)
                ->build();
        }
        return $personsArr;
    }

    //2-search for institutions by names
    public function searchInstitutionsByName(string $name): array //of Institutions
    {
        $conn = $this->getDatabaseConnection();
        $sql = "SELECT * FROM Institution WHERE institution_name=?";
        $params = array($name);
        $stmt = $this->getParameterizedStatement($sql, $conn, $params);
        $array_of_institutions = array();
        if ($stmt == false) {
            $error = sqlsrv_errors()[0]['message'];
            $this->closeConnection($conn);
            throw  new SQLStatmentException($error);
        }
        while ($row = sqlsrv_fetch_object($stmt)) {
            $array_of_institutions[] = Institution::Builder()->setID((int)$row['ID'])
                ->setName((string)$row->institution_name)
                ->setWebsite((string)$row->institution_website)
                ->setInstitutionImg((string)$row->institution_img)
                ->build();
        }
        $this->closeConnection($conn);
        return $array_of_institutions;

    }




}