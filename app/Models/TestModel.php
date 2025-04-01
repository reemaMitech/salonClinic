<?php

namespace App\Models;

use CodeIgniter\Model;

class TestModel extends Model
{
    protected $table = 'CRUDSP';  // Replace with your actual table name


// Function to execute the stored procedure
public function executeProcedure($id, $name, $age, $address, $type)
{
    $sql = "EXEC usp_Employee_crud ?, ?, ?, ?, ?";
    $params = [$id, $name, $age, $address, $type];

    if ($type === 'select') {
        return $this->db->query($sql, $params)->getResult();  // Fetch result for select
    } else {
        $this->db->query($sql, $params);  // Execute for insert, update, delete
        return true;  // Return true to confirm the operation
    }
}
}

?>