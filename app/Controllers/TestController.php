<?php

namespace App\Controllers;

use App\Models\TestModel;
use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;  // Properly include ResponseTrait

class TestController extends Controller
{
    // public function index()
    // {   
    //     $db = \Config\Database::connect();
    //     $model = new TestModel();
    //     $data = $model->findAll(); // Fetch all records from the table
        
    //     echo '<pre>';
    //     print_r($data);  // Output the data to check the connection
    //     echo '</pre>';
    // }

    use ResponseTrait;

    public function index()
    {
        $id = $this->request->getPost('id');
        $name = $this->request->getPost('name');
        $age = $this->request->getPost('age');
        $address = $this->request->getPost('address');
        $type = $this->request->getPost('type');
    
        $model = new TestModel();
        $result = $model->executeProcedure($id, $name, $age, $address, $type);
    
        if ($type === 'select') {
            if ($result) {
                return $this->respond($result);  // Return the data for 'select' type
            } else {
                return $this->failNotFound('No records found');
            }
        } else {
            return $this->respond(['status' => 'success', 'message' => ucfirst($type) . ' operation completed successfully']);
        }
    }

}


?>
