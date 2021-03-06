<?php
/*require_once __DIR__.'/../../Paths.php';
require_once EXCEPTIONS_BASE_PATH . '/CannotCreateHigherEmployeeException.php';
require_once EXCEPTIONS_BASE_PATH . '/ConnectionException.php';
require_once EXCEPTIONS_BASE_PATH . '/DataNotFound.php';
require_once EXCEPTIONS_BASE_PATH . '/DuplicateDataEntry.php';
require_once EXCEPTIONS_BASE_PATH . '/FileHandlerException.php';
require_once EXCEPTIONS_BASE_PATH . '/FileNotFoundException.php';
require_once EXCEPTIONS_BASE_PATH . '/FolderUploadingSqlException.php';
require_once EXCEPTIONS_BASE_PATH . '/InsertionError.php';
require_once EXCEPTIONS_BASE_PATH . '/LogsError.php';
require_once EXCEPTIONS_BASE_PATH . '/LowRoleForSuchActionException.php';
require_once EXCEPTIONS_BASE_PATH . '/NoNotificationsFoundException.php';
require_once EXCEPTIONS_BASE_PATH . '/NoPermissionsGrantedException.php';
require_once EXCEPTIONS_BASE_PATH . '/PermissionsCriticalFail.php';
require_once EXCEPTIONS_BASE_PATH . '/PersonHasNoRolesException.php';
require_once EXCEPTIONS_BASE_PATH . '/PersonOrDeactivated.php';
require_once EXCEPTIONS_BASE_PATH . '/SearchQueryInsuffecient.php';
require_once EXCEPTIONS_BASE_PATH . '/SQLStatmentException.php';
require_once FILE_MANAGEMENT_BASE_PATH."/FileRepoHandler.php";
require_once VALIDATION_BASE_PATH."/PersonValidator.php";
require_once ENCRYPTION_BASE_PATH."/EncryptionManager.php";
require_once PERMISSIONS_BASE_PATH."/PersonPermissions.php";
require_once PERMISSIONS_BASE_PATH."/InstitutionsPermissions.php";
require_once SESSIONS_BASE_PATH."/SessionManager.php";
require_once BUSINESS_BASE_PATH."/Institution.php";
require_once BUSINESS_BASE_PATH."/Person.php";
require_once BUSINESS_BASE_PATH."/PersonRole.php";
require_once BUSINESS_BASE_PATH."/City.php";*/



abstract class Action
{
    protected Person $myPersonRef;

    /**
     * @return Person
     */
    public function getMyPersonRef(): Person
    {
        return $this->myPersonRef;
    }
    public function getInstitutionIDByName(string $getName):int
    {

        $conn=$this->getDatabaseConnection();
        $sql = "SELECT ID FROM Institution WHERE institution_name =?";
        $params = array($getName);
        $stmt = $this->getParameterizedStatement($sql, $conn, $params);
        if ($stmt == false || !sqlsrv_has_rows($stmt)) {
            //TODO :PRODUCTION UNCOMMENT THIS
            //$error="Error fetching the required data";
            $error=sqlsrv_errors()[0]['message'];
            sqlsrv_close($conn);

            throw new SQLStatmentException($error);
        }
        else
        {
            $row=sqlsrv_fetch_object($stmt);
            return (int)$row->ID;
        }
    }
    protected function getSingleStatement(String $query,&$conn)
    {
        $stmt=sqlsrv_query($conn,$query);
        if(!$stmt)
        {
            $error=sqlsrv_errors()[0];
            throw new SQLStatmentException($error['message']);
        }
        else return $stmt;
    }
    protected function getParameterizedStatement(String $query,&$conn,array $params)
    {
        try {
            return sqlsrv_query($conn, $query, $params);

        }
        catch (Exception $e)
        {
            $error = sqlsrv_errors()[0];
            throw new SQLStatmentException($error['message']);

        }
    }


    public  function getInstitutionNameByID(int $id): String
    {
        $con=$this->getDatabaseConnection();
        $sql = "SELECT institution_name FROM Institution WHERE ID=?";
        $params = array($id);
        $stmt = $this->getParameterizedStatement($sql, $con, $params);
        if ($stmt == false || !sqlsrv_has_rows($stmt)) {
            $this->closeConnection($conn);
            throw new SQLStatmentException("Error fetching the required data");
        }
        else
        {
            $row=sqlsrv_fetch_object($stmt);
            return (string)$row->institution_name;
        }

    }

    public function isUserExists(string $academicNumberOrEmail): bool
    {
        $conn = $this->getDatabaseConnection();
        $sql = "SELECT * FROM Person WHERE Person.academic_number=? OR Person.contact_email=?";
        $params = array($academicNumberOrEmail,$academicNumberOrEmail);
        $stmt = $this->getParameterizedStatement($sql, $conn, $params);

        if ($stmt == false) {
            //TODO :PRODUCTION UNCOMMENT THIS
            //$error="Couldn't execute this statement";
            $error=sqlsrv_errors()[0]['message'];
            $this->closeConnection($conn);
            throw new SQLStatmentException($error);
        }
        if (sqlsrv_has_rows($stmt)) {
            $this->closeConnection($conn);
            $row=sqlsrv_fetch_object($stmt);
            return true;
        }
        $this->closeConnection($conn);

        return false;
    }

    protected function getIdFromPersonEmail(string $targetEmail): int
    {
        $conn = $this->getDatabaseConnection();
        $sql = "SELECT ID FROM Person WHERE contact_email=?";
        $params = array($targetEmail);
        $stmt = $this->getParameterizedStatement($sql, $conn, $params);
        if ($stmt == false || !sqlsrv_has_rows($stmt)) {
            $this->closeConnection($conn);
            throw new SQLStatmentException("Could not get details of the person id");
        }
        $id = sqlsrv_fetch_array($stmt)[0][0];
        $this->closeConnection($conn);
        return $id;
    }
    public function getBaseFacultyOfPerson(string $personEmail):Institution
    {
        $conn=$this->getDatabaseConnection();
        $sql="SELECT base_faculty FROM PersonContacts WHERE email=?";
        $params=array($personEmail);
        $stmt=$this->getParameterizedStatement($sql,$conn,$params);
        if($stmt==false || !sqlsrv_has_rows($stmt))
        {
            $error=sqlsrv_errors()[0]['message'];
            $this->closeConnection($conn);
            throw new SQLStatmentException($error);
        }
        $row=sqlsrv_fetch_object($stmt);
        return Institution::Builder()->setName($row->base_faculty)->build();

    }

    public function getRolesOfPerson(string $personEmail):array
    {
        $conn=$this->getDatabaseConnection();
        $sql="SELECT ID,role_front_name,role_priority_lvl,institution_name FROM [dbo].[PersonRolesAndPermissions_view] WHERE active=1 AND contact_email=?";
        $params=array($personEmail);
        $stmt=$this->getParameterizedStatement($sql,$conn,$params);
        if($stmt==false)
        {

            //TODO :PRODUCTION UNCOMMENT THIS
            //$error="Could not get the roles of this person";
            $error=sqlsrv_errors()[0]['message'];
            $this->closeConnection($conn);
            throw new PersonHasNoRolesException($error);

        }
        $roles=array();
        while ($row=sqlsrv_fetch_object($stmt))
        {
            $roles[]= PersonRole::Builder()->setPriorityLevel((int)$row->role_priority_lvl)
            ->setInstitutionName((string)$row->institution_name)
            ->setJobTitle((string)$row->role_front_name)
            ->setID((int)$row->ID)
            ->build();
                 }
        return $roles;

    }

    public function isEmployeeOfInstitution(int $getInstitutionID) : bool
    {

        $conn = $this->getDatabaseConnection();
        $sql = "SELECT person_id FROM Employees WHERE person_id=? AND institution_id=? AND active=1";
        $params = array($this->myPersonRef->getID(),$getInstitutionID);
        $stmt = $this->getParameterizedStatement($sql, $conn, $params);
        if ($stmt == false ) {
            $this->closeConnection($conn);
            return false;
        }
        if(!sqlsrv_has_rows($stmt))
        {
            throw new PersonOrDeactivated("Your are not a part of this institution");

        }
        $id = sqlsrv_fetch_array($stmt)[0];

        $this->closeConnection($conn);
        if($id==$this->myPersonRef->getID())
        {
            return true;
        }
        else{return false;}

    }
    protected function getEmailFromPersonId(int $id): String
    {
        $conn = $this->getDatabaseConnection();
        $sql = "SELECT contact_email FROM Person WHERE ID=?";
        $params = array($id);
        $stmt = $this->getParameterizedStatement($sql, $conn, $params);
        if ($stmt == false || !sqlsrv_has_rows($stmt)) {
            $this->closeConnection($conn);
            throw new SQLStatmentException("Could not get details of the person id");
        }
        $email = sqlsrv_fetch_array($stmt)[0][0];
        $this->closeConnection($conn);
        return $email;
    }
    protected function getSpecificEmployee(int $person_id,int $institution_id,int $role_id): PersonRole
    {
        $conn=$this->getDatabaseConnection();
        $sql='SELECT * FROM Employees WHERE person_id=? AND institution_id=? AND role_id=? INNER JOIN Roles ON Employees.role_id=Roles.ID;';
        $params=array($person_id,$institution_id,$role_id);
        $stmt=$this->getParameterizedStatement($sql,$conn,$params);
        if($stmt==false)
        {
            //TODO :PRODUCTION UNCOMMENT THIS
            //$error="Could not get the roles of this person";
            $error=sqlsrv_errors()[0]['message'];
            $this->closeConnection($conn);
            throw new PersonHasNoRolesException($error);
        }
        if(!sqlsrv_has_rows($stmt))
        {
            $error="Could Not Find This Employee";
            $this->closeConnection($conn);
            throw new DataNotFound($error);
        }
        $row=sqlsrv_fetch_object($stmt);
        $active=(bool)$row->active;
        if(!$active)
        {
            $error="This Employee Is Deactivated";
            $this->closeConnection($conn);
            throw new PersonOrDeactivated($error);
        }
        $personRole=PersonRole::Builder()->setID($role_id)
            ->setPriorityLevel($row->role_priority_lvl)
            ->setPersonsPermissionsSum($row->sada)
            ->setInstitutionsPermissionsSum($row->sada)
            ->setFilesPermissionsSum($row->sada)
            ->setFoldersPermissionsSum($row->sada)
            ->build();
        $this->closeConnection($conn);
        return $personRole;



    }


    protected function getNameFromPersonId(int $id): String
    {
        $conn = $this->getDatabaseConnection();
        $sql = "SELECT first_name,last_name FROM Person WHERE ID=?";
        $params = array($id);
        $stmt = $this->getParameterizedStatement($sql, $conn, $params);
        if ($stmt == false || !sqlsrv_has_rows($stmt)) {
            $this->closeConnection($conn);
            throw new SQLStatmentException("Could not get details of the person id");
        }
        $first = sqlsrv_fetch_array($stmt)[0][0];
        $second = sqlsrv_fetch_array($stmt)[0][1];

        $this->closeConnection($conn);
        return $first." ".$second;
    }






    protected function closeConnection(&$conn)
    {

        try {

            sqlsrv_close($conn);
        } catch (Exception $e) {
        }
    }

     private String $SERVER_NAME;
     private array $connectionInfo;

    /**
     *
     *
     * Action constructor.
     */

    /**
     */
    public function getDatabaseConnection()
    {

        return  sqlsrv_connect($this->SERVER_NAME, $this->connectionInfo);
    }

    protected function setConnection(Action $action)
    {
        $this->connectionInfo= array("UID" => "ziadmohamd456", "pwd" => "{01015790817aA}", "Database" => "DMS_db", "LoginTimeout" => 30, "Encrypt" => 1, "TrustServerCertificate" => 0,"CharacterSet" => "UTF-8");
        $this->SERVER_NAME= "tcp:dms-kfs1.database.windows.net,1433";

    }




/*$conn=$this->getDatabaseConnection();
$sql='';
$params=array();
$stmt=$this->getParameterizedStatement($sql,$conn,$params);
if($stmt==false)
{
    //TODO :PRODUCTION UNCOMMENT THIS
    //$error="Could not get the roles of this person";
$error=sqlsrv_errors()[0]['message'];
$this->closeConnection($conn);
throw new PersonHasNoRolesException($error);
}
*/
}
