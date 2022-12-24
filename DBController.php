<?php
class DBController
{
    private $host = "localhost";
    private $user = "southesk_bpn";
    private $password = "__REMOVED_SEE_config.php__";
    private $database = "phpsamples";

    private $conn;

    function __construct()
    {
        $this->conn = $this->connectDB();
    }

    function connectDB()
    {
        $conn = mysqli_connect($this->host, $this->user, $this->password, $this->user);
        return $conn;
    }

    function runQuery($query)
    {
        $result = mysqli_query($this->conn, $query);
        while ($row = mysqli_fetch_array($result)) {
            $resultset[] = $row;
        }
        if (! empty($resultset))
            return $resultset;
    }
}
?>