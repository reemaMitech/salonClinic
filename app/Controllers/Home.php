<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use Config\App;
use DateTime;

require_once ROOTPATH . 'public/JWT/src/JWT.php';

use Config\Database;
use CodeIgniter\Controller;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Exception;
use Config\Services;

helper('email_helper');

require_once FCPATH . 'vendor/autoload.php';

use App\Models\HomeModel;

use DateTimeZone;

class Home extends BaseController
{

    protected $db;
    protected $key = 'your_secret_key';
    protected $uri;
    protected $modelName = 'App\Models\HomeModel';
    protected $format    = 'json';
    protected $homeModel;
    use ResponseTrait;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
        $this->uri = service('uri');
        // $this->homeModel = new HomeModel(); 
        $this->homeModel = new HomeModel();
        if ($this->homeModel === null) {
            log_message('error', 'HomeModel could not be instantiated.');
        }
    }
    public function optionsMethod()
    {
        return $this->response->setStatusCode(200)
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept')
            ->setHeader('Access-Control-Max-Age', '86400'); // Cache for 1 day
    }


    // public function authenticate()
    // {
    //     // Set the content type to JSON
    //     $this->response->setHeader('Content-Type', 'application/json');

    //     // Parse the input JSON
    //     $input = json_decode($this->request->getBody(), true);

    //     // Validate JSON input format
    //     if (!$input) {
    //         return $this->response->setJSON([
    //             'status' => 400,
    //             'message' => 'Invalid input format.'
    //         ])->setStatusCode(400);
    //     }

    //     $mobile = trim($input['mobile'] ?? '');
    //     $password = trim($input['password'] ?? '');

    //     // Check if required fields are provided
    //     if (empty($mobile) || empty($password)) {
    //         return $this->response->setJSON([
    //             'status' => 400,
    //             'message' => 'Mobile number and Password are required.'
    //         ])->setStatusCode(400);
    //     }

    //     // Look up the user in the database based on mobile number
    //     $user = $this->db->table('tbl_register')
    //              ->where('mobile', $mobile)
    //              ->where('is_deleted', 'N')

    //              ->get()
    //              ->getRowArray();

    //     // If user is not found or password does not match, return error
    //     if (!$user || !password_verify($password, $user['password'])) {
    //         return $this->response->setJSON([
    //             'status' => 401,
    //             'message' => 'Invalid mobile number or password.'
    //         ])->setStatusCode(401);
    //     }

    //     // Create payload for JWT token
    //     $payload = [
    //         'iat' => time(),
    //         'exp' => time() + (2 * 60),
    //         'user_id' => $user['id'],
    //         'name' => $user['name'],
    //         'mobile' => $user['mobile'],
    //         'role' => $user['role']
    //     ];
    //     $token = JWT::encode($payload, $this->key, 'HS256');

    //     // Return JSON response with token
    //     return $this->response->setJSON([
    //         'userid' => $user['id'],
    //         'status' => 200,
    //         'message' => 'Authentication successful',
    //         'token' => $token,
    //         'role' => $user['role']
    //     ])->setStatusCode(200);
    // }
    public function authenticate()
    {
        $this->response->setHeader('Content-Type', 'application/json');
        $input = json_decode($this->request->getBody(), true);
        if (!$input) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'Invalid input format.'
            ])->setStatusCode(400);
        }
        $mobile = trim($input['mobile'] ?? '');
        $password = trim($input['password'] ?? '');
        if (empty($mobile) || empty($password)) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'Mobile number and Password are required.'
            ])->setStatusCode(400);
        }
        $config = new App();
        $baseURL = $config->baseURL;

        $secondDB = Database::connect('secondDB');
        $productKeyRecord = $secondDB->table('tbl_opdproductkey')
            ->where('Project_Url', $baseURL)
            // ->where('is_deleted', 'N')
            ->get()
            ->getRowArray();
        // print_r($productKeyRecord);die;
        if (!$productKeyRecord) {
            return $this->response->setJSON([
                'status' => 403,
                'message' => 'Subscription not found for this project URL.'
            ])->setStatusCode(403);
        }
        $encryption = Services::encrypter();

        try {
            $decodedKey = $encryption->decrypt(base64_decode($productKeyRecord['productkey']));
            $parts = explode('-', $decodedKey);
            $startDate = implode('-', array_slice($parts, 0, 3));
            $endDate = implode('-', array_slice($parts, 3, 3));
            // print_r($endDate);die;
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'Invalid product key.'
            ])->setStatusCode(500);
        }
        $today = date('Y-m-d');
        if ($today > $endDate) {
            return $this->response->setJSON([
                'status' => 403,
                'message' => 'Your subscription has ended.'
            ])->setStatusCode(403);
        }
        $user = $this->db->table('tbl_login')
            ->where('mobile', $mobile)
            ->where('is_active', 'Y')
            ->get()
            ->getRowArray();
        // print_r($user);exit();
        if (!$user || !password_verify($password, $user['password'])) {
            return $this->response->setJSON([
                'status' => 401,
                'message' => 'Invalid mobile number or password.'
            ])->setStatusCode(401);
        }

        // Initialize access levels
        $accessLevels = [];

        if ($user['role_ref_code'] === 'STL') {
            $stylistData = $this->db->table('tbl_stylists')
                ->where('id', $user['user_code_ref'])
                ->where('is_active', 'Y')
                ->where('is_deleted', 'N')
                ->get()
                ->getRowArray();

            if ($stylistData) {
                $accessLevels = json_decode($stylistData['access_levels'], true); // Convert string to array
            }
        }
        // print_r($accessLevels);exit();
        $payload = [
            'iat' => time(),
            'exp' => time() + (2 * 60),
            'user_id' => $user['id'],
            // 'name' => $user['name'],
            'mobile' => $user['mobile'],
            'role_ref_code' => $user['role_ref_code']
        ];
        $token = JWT::encode($payload, $this->key, 'HS256');

        // Prepare response data
        return $this->response->setJSON([
            'userid' => $user['id'],
            'status' => 200,
            'message' => 'Authentication successful',
            'token' => $token,
            'role_ref_code' => $user['role_ref_code'],
            'access_levels' => $accessLevels
        ])->setStatusCode(200);
    }
    // Verify JWT token
    public function verifyToken()
    {
        $authHeader = $this->request->getHeader('Authorization');
        $token = $authHeader ? $authHeader->getValue() : null;

        if (!$token) {
            return $this->response->setJSON([
                'status' => 401,
                'message' => 'Token not provided'
            ])->setStatusCode(401);
        }

        try {
            $token = str_replace('Bearer ', '', $token);
            $decoded = JWT::decode($token, new Key($this->key, 'HS256'));

            // Token is valid, return success message
            return $this->response->setJSON([
                'status' => 200,
                'message' => 'Token is valid',
                'data' => (array) $decoded  // Return decoded token data
            ])->setStatusCode(200);
        } catch (\Exception $e) {
            // Token is invalid or expired
            return $this->response->setJSON([
                'status' => 401,
                'message' => 'Invalid token: ' . $e->getMessage()
            ])->setStatusCode(401);
        }
    }
    public function savescedule($table)
    {
        $input = $this->request->getJSON();
        log_message('debug', 'Request Data: ' . json_encode($input));

        if (empty($input)) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'No data provided.'
            ])->setStatusCode(400);
        }

        // Convert JSON to associative array
        $data = json_decode(json_encode($input), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'Invalid JSON input.'
            ])->setStatusCode(400);
        }

        // Validate input
        if (!isset($data['schedules']) || !is_array($data['schedules']) || count($data['schedules']) === 0) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'No valid schedules provided.'
            ])->setStatusCode(400);
        }

        // Prepare database tables
        $builderSlots = $this->db->table($table);
        $builderSchedule = $this->db->table('tbl_schedule');

        // Extract branch and service ID
        $branchId = isset($data['schedules'][0]['branch']) ? $data['schedules'][0]['branch'] : null;
        // $serviceId = isset($data['service_id']) ? $data['service_id'] : null; 

        if (!$branchId) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'Branch ID is required.'
            ])->setStatusCode(400);
        }

        foreach ($data['schedules'] as $schedule) {
            if (empty($schedule['startTime']) || empty($schedule['endTime'])) {
                continue; // Skip empty schedules
            }

            $bufferTime = isset($schedule['bufferTime']) ? $schedule['bufferTime'] : null;
            $price = isset($schedule['Price']) ? $schedule['Price'] : null;
            $slotCount = isset($schedule['slotcount']) ? $schedule['slotcount'] : null;

            // Insert into `tbl_schedule`
            $scheduleData = [
                'start_time' => $schedule['startTime'],
                'end_time' => $schedule['endTime'],
                'branch_id' => $branchId,
                // 'service_id' => $serviceId,
                'day_name' => $schedule['day'],
                'buffer_time' => $bufferTime,
                'price' => $price,
                'slotcount' => $slotCount,
                'is_deleted' => 'N',
                'created_at' => date('Y-m-d H:i:s')
            ];

            if ($builderSchedule->insert($scheduleData)) {
                $scheduleId = $this->db->insertID(); // Get last inserted ID
            } else {
                return $this->response->setJSON([
                    'status' => 500,
                    'message' => 'Schedule creation failed for day: ' . $schedule['day']
                ])->setStatusCode(500);
            }

            // Insert slots into `tbl_slots`
            foreach ($schedule['slots'] as $slot) {
                $slotData = [
                    'slots_time' => $slot,
                    'day_name' => $schedule['day'],
                    'branch_id' => $branchId,
                    // 'service_id' => $serviceId,
                    'slotcount' => $slotCount,
                    'price' => $price,
                    'schedule_id' => $scheduleId
                ];

                if (!$builderSlots->insert($slotData)) {
                    return $this->response->setJSON([
                        'status' => 500,
                        'message' => 'Slot creation failed for slot: ' . $slot . ' on day: ' . $schedule['day']
                    ])->setStatusCode(500);
                }
            }
        }

        return $this->response->setJSON([
            'status' => 201,
            'message' => 'Records created successfully'
        ])->setStatusCode(201);
    }


    //     public function create($table)
    // public function create($table)
    // {
    //     $input = $this->request->getJSON();

    //     if (empty($input)) {
    //         return $this->response->setJSON([
    //             'status' => 400,
    //             'message' => 'No data provided.'
    //         ])->setStatusCode(400);
    //     }

    //     // Convert input to an array
    //     $data = (array) $input;

    //     log_message('debug', 'Received Data in create(): ' . json_encode($data));


    //     // Check if inserting into 'tbl_register' and hash the password
    //     if ($table === 'tbl_register') {
    //         if (isset($data['password'])) {
    //             $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
    //         }

    //         // Assign default access levels if not provided
    //         if (!isset($data['access_levels'])) {
    //             $data['access_levels'] = [4,5,7,9,10,11,12,13,14,15,1]; 
    //         }
    //     }

    //     // Convert modified data to JSON string
    //     $jsonData = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


    //     // Execute stored procedure
    //     $query = $this->db->query("CALL dynamic_insert(?, ?::jsonb)", [$table, $jsonData]);

    //     if ($query) {
    //         return $this->response->setJSON([
    //             'status' => 201,
    //             'message' => 'Record created successfully'
    //         ])->setStatusCode(201);
    //     }

    //     return $this->response->setJSON([
    //         'status' => 500,
    //         'message' => 'Record creation failed'
    //     ])->setStatusCode(500);
    // }

    public function create($table)
    {
        $input = $this->request->getJSON(true); // Get data as associative array

        if (empty($input)) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'No data provided.'
            ])->setStatusCode(400);
        }

        // Convert input to an array
        $data = (array) $input;

        log_message('debug', 'Received Data in create(): ' . json_encode($data));

        // Special handling for tbl_register
        if ($table === 'tbl_register') {
            if (isset($data['password'])) {
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            if (!isset($data['access_levels'])) {
                $data['access_levels'] = [4, 5, 7, 9, 10, 11, 12, 13, 14, 15, 1];
            }
        }

        // Special handling for tbl_chairs with multiple inserts
        if ($table === 'tbl_chairs' && isset($data['chairs']) && is_array($data['chairs'])) {
            foreach ($data['chairs'] as $item) {
                $this->db->table($table)->insert($item);
            }

            return $this->response->setJSON([
                'status' => 201,
                'message' => 'Chairs created successfully'
            ])->setStatusCode(201);
        }

        // Convert to JSON for PostgreSQL
        $jsonData = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        log_message('debug', "Insert Data: " . $jsonData);

        // Prepare dynamic query based on table
        if ($table === 'tbl_branch') {
            // Expecting returned ID
            $query = $this->db->query("SELECT * FROM dynamic_insert(?, ?::jsonb, ?)", [$table, $jsonData, true]);
            $result = $query->getRow();

            return $this->response->setJSON([
                'status' => 201,
                'message' => 'Branch created successfully',
                'branch_id' => $result->id ?? null
            ])->setStatusCode(201);
        } else {
            // No ID returned for other tables
            $query = $this->db->query("CALL dynamic_insert(?, ?::jsonb)", [$table, $jsonData]);

            if ($query) {
                return $this->response->setJSON([
                    'status' => 201,
                    'message' => 'Record created successfully'
                ])->setStatusCode(201);
            }

            return $this->response->setJSON([
                'status' => 500,
                'message' => 'Record creation failed'
            ])->setStatusCode(500);
        }
    }













    public function read($table, $id = null)
    {
        try {
            $db = \Config\Database::connect();
            if ($id) {
                $query = $db->query("CALL spReadData(?, ?, ?)", [$id, $table, NULL]);
            } else {
                $query = $db->query("CALL spReadData(NULL, ?, ?)", [$table, NULL]);
            }
            $result = $query->getRowArray();

            if ($result && !empty($result['result_json'])) {
                return $this->response->setJSON([
                    'status' => 200,
                    'message' => 'Records retrieved successfully',
                    'data' => json_decode($result['result_json'], true)
                ])->setStatusCode(200);
            }
            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No records found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }
    // public function createcunsltant($table, $id = null)
    // {
    //     $input = $this->request->getJSON();
    //     if (empty($input)) {
    //         return $this->response->setJSON([
    //             'status' => 400,
    //             'message' => 'No data provided.'
    //         ])->setStatusCode(400);
    //     }
    //     $db = \Config\Database::connect();
    //     $name = (string)$input->name;
    //     $degree = (string)$input->degree;
    //     $section = intval($input->section); // Ensure this is an integer
    //     $role = (string)$input->role;
    //     $password = password_hash($input->password, PASSWORD_BCRYPT); // Hashed password
    //     $mobile = (string)$input->mobile; // Mobile number

    //     try {
    //         if ($id > 0) {
    //             $query = "CALL sp_manage_Consultant(
    //                 CAST(? AS INTEGER),  -- ID will be null, triggering insert
    //                 CAST(? AS VARCHAR),
    //                 CAST(? AS VARCHAR),
    //                 CAST(? AS INTEGER),
    //                 CAST(? AS VARCHAR),
    //                 CAST(? AS VARCHAR),
    //                 CAST(? AS VARCHAR)
    //             )";
    //             $db->query($query, [
    //                 $id, // NULL for insert
    //                 $name,
    //                 $degree,
    //                 $section,
    //                 $role,
    //                 $password,
    //                 $mobile
    //             ]);
    //         } else {
    //             $query = "CALL sp_manage_Consultant(
    //                 CAST(? AS INTEGER),  -- Provided ID, triggering update
    //                 CAST(? AS VARCHAR),
    //                 CAST(? AS VARCHAR),
    //                 CAST(? AS INTEGER),
    //                 CAST(? AS VARCHAR),
    //                 CAST(? AS VARCHAR),
    //                 CAST(? AS VARCHAR)
    //             )";

    //             $db->query($query, [
    //                 $id, // The existing ID for update
    //                 $name,
    //                 $degree,
    //                 $section,
    //                 $role,
    //                 $password,
    //                 $mobile
    //             ]);
    //         }   
    //         return $this->response->setJSON([
    //             'status' => 201,
    //             'message' => 'Operation completed successfully.'
    //         ])->setStatusCode(201);

    //     } catch (\Exception $e) {
    //         // Catch any errors and return error message
    //         return $this->response->setJSON([
    //             'status' => 500,
    //             'message' => 'Error: ' . $e->getMessage()
    //         ])->setStatusCode(500);
    //     }
    // }
    public function createConsultant()
    {
        $input = $this->request->getJSON();
        if (empty($input)) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'No data provided.'
            ])->setStatusCode(400);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $consultantId = isset($input->id) && !empty($input->id) ? (int)$input->id : 0;
        $name = $input->name ?? '';
        $degree = $input->degree ?? '';
        $password = isset($input->password) ? password_hash($input->password, PASSWORD_BCRYPT) : null;
        $mobile = $input->mobile ?? '';
        $services = json_encode($input->services ?? []);

        if ($consultantId > 0) {
            // Update existing consultant
            $db->query("CALL sp_manage_consultant(?, ?, ?, ?, ?, ?)", [
                $consultantId,
                $name,
                $degree,
                $password,
                $mobile,
                $services
            ]);
        } else {
            // Insert new consultant
            $db->query("CALL sp_manage_consultant(?, ?, ?, ?, ?, ?)", [
                &$consultantId,
                $name,
                $degree,
                $password,
                $mobile,
                $services
            ]);

            // Fetch the last inserted consultant ID
            $query = $db->query("SELECT currval('tbl_consultants_id_seq') AS id");
            $row = $query->getRow();
            if ($row && isset($row->id)) {
                $consultantId = $row->id;
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'Error processing consultant data.'
            ])->setStatusCode(500);
        }

        return $this->response->setJSON([
            'status' => 200,
            'message' => $consultantId > 0 ? 'Consultant updated successfully.' : 'Consultant created successfully.',
            'consultant_id' => $consultantId
        ]);
    }





    public function createLoginEntry()
    {
        $input = $this->request->getJSON();
        // print_r($input);exit();
        if (empty($input)) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'No data provided.'
            ])->setStatusCode(400);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $mobile = $input->mobile ?? '';
        $password = password_hash($input->password, PASSWORD_BCRYPT);
        $role_ref_code = 'STL';
        $user_code_ref = $input->consultant_id ?? 0;

        if (!$user_code_ref) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'Consultant ID is required.'
            ])->setStatusCode(400);
        }

        // Call stored procedure for tbl_login
        $sql = "CALL sp_manage_login(?, ?, ?, ?, ?)";
        $db->query($sql, [$mobile, $password, $role_ref_code, $user_code_ref, &$loginId]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'Error creating login entry.'
            ])->setStatusCode(500);
        }

        return $this->response->setJSON([
            'status' => 200,
            'message' => 'Login entry created successfully.',
            'login_id' => $loginId
        ]);
    }


    // public function createcunsltant($table, $id = null)
    // {
    //     log_message('debug', "Received request for table: $table, ID: " . ($id ?? "NEW"));

    //     $input = $this->request->getJSON(true); // Convert JSON to array
    //     if (empty($input)) {
    //         return $this->response->setJSON([
    //             'status' => 400,
    //             'message' => 'No data provided.'
    //         ])->setStatusCode(400);
    //     }

    //     return $this->response->setJSON([
    //         'status' => 200,
    //         'message' => "Function hit successfully! Table: $table, ID: " . ($id ?? "NEW"),
    //         'data' => $input
    //     ]);
    // }








    // public function update($table, $id)
    // {
    //     $input = json_decode(file_get_contents('php://input'), true);

    //     if (!is_array($input) || empty($input)) {
    //         return $this->response->setJSON([
    //             'status' => 400,
    //             'message' => 'No valid input data provided'
    //         ])->setStatusCode(400);
    //     }

    //     if (isset($input[0]) && is_array($input[0])) {
    //         $input = $input[0]; 
    //     }

    //     // Prepare the JSON data to be passed to the stored procedure
    //     $jsonData = json_encode($input);

    //     // Call the dynamic_update stored procedure
    //     $db = \Config\Database::connect();
    //     try {
    //         $db->query("CALL dynamic_update(?, ?::jsonb, ?)", [$table, $jsonData, $id]);
    //         return $this->response->setJSON([
    //             'status' => 200,
    //             'message' => 'Record updated successfully'
    //         ])->setStatusCode(200);
    //     } catch (\Exception $e) {
    //         return $this->response->setJSON([
    //             'status' => 500,
    //             'message' => 'Record update failed: ' . $e->getMessage()
    //         ])->setStatusCode(500);
    //     }
    // }


    // public function update($table, $id)
    // {
    //     $input = json_decode(file_get_contents('php://input'), true);

    //     if (!is_array($input) || empty($input)) {
    //         return $this->response->setJSON([
    //             'status' => 400,
    //             'message' => 'No valid input data provided'
    //         ])->setStatusCode(400);
    //     }

    //     if (isset($input[0]) && is_array($input[0])) {
    //         $input = $input[0]; 
    //     }

    //     $db = \Config\Database::connect();

    //     try {
    //         // Identify fields that should be stored as PostgreSQL arrays
    //         $arrayFields = ['chair_ids', 'bed_ids']; // Add more field names if needed

    //         foreach ($arrayFields as $field) {
    //             if (isset($input[$field]) && is_array($input[$field])) {
    //                 // Convert array to PostgreSQL format: {C1,C2} instead of ["C1","C2"]
    //                 $input[$field] = '{' . implode(',', $input[$field]) . '}';
    //             }
    //         }

    //         // Convert full input data to JSON
    //         $jsonData = json_encode($input, JSON_UNESCAPED_UNICODE);

    //         // Execute the stored procedure
    //         $query = "CALL dynamic_update(?, CAST(? AS jsonb), ?)";
    //         $db->query($query, [$table, $jsonData, $id]);

    //         return $this->response->setJSON([
    //             'status' => 200,
    //             'message' => 'Record updated successfully'
    //         ])->setStatusCode(200);
    //     } catch (\Exception $e) {
    //         return $this->response->setJSON([
    //             'status' => 500,
    //             'message' => 'Record update failed: ' . $e->getMessage()
    //         ])->setStatusCode(500);
    //     }
    // }

    public function update($table, $id)
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input) || empty($input)) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'No valid input data provided'
            ])->setStatusCode(400);
        }

        // If input is wrapped in an array (e.g., [{"field": "value"}]), unwrap it
        if (isset($input[0]) && is_array($input[0])) {
            $input = $input[0];
        }

        $db = \Config\Database::connect();

        try {
            // Special JSON fields (no transformation needed now)
            $jsonFields = ['chair_ids', 'bed_ids'];

            // If the table is tbl_branch, ensure fields stay as JSON arrays
            if ($table === 'tbl_branch') {
                foreach ($jsonFields as $field) {
                    if (isset($input[$field]) && is_array($input[$field])) {
                        // Just ensure it's properly encoded in the final JSON
                        continue; // No transformation needed
                    }
                }
            }

            // Convert the full input data to JSON (preserve arrays)
            $jsonData = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // Call your dynamic_update stored procedure
            $query = "CALL dynamic_update(?, CAST(? AS jsonb), ?)";
            $db->query($query, [$table, $jsonData, $id]);

            return $this->response->setJSON([
                'status' => 200,
                'message' => 'Record updated successfully'
            ])->setStatusCode(200);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'Record update failed: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }




    private function getTableColumns($table)
    {
        return $this->db->getFieldNames($table);
    }

    public function delete($table, $id)
    {
        // Execute the stored procedure for soft delete
        $query = "CALL dynamic_delete('$table', $id)";
        if ($this->db->query($query)) {
            return $this->response->setJSON([
                'status' => 200,
                'message' => 'Record marked as deleted successfully'
            ])->setStatusCode(200);
        }
        return $this->response->setJSON([
            'status' => 500,
            'message' => 'Failed to mark the record as deleted'
        ])->setStatusCode(500);
    }

    // public function fetchslots()
    // {
    //     $rawInput = file_get_contents('php://input');
    //     $input = json_decode($rawInput, true); 
    //     $selected_date = $input['selected_date'];
    //     $branch_id = $input['branch_id'];
    //     $day_name = DateTime::createFromFormat('Y-m-d', $selected_date)->format('l');
    //     $timezone = new DateTimeZone('Asia/Kolkata');
    //     $current_time = new DateTime('now', $timezone);
    //     $current_date = $current_time->format('Y-m-d');
    //     $is_today = ($selected_date === $current_date);

    //     // 1. Check for holiday
    //     $holiday = $this->db->table('tbl_holiday')
    //         ->where(['date' => $selected_date, 'is_deleted' => 'N'])
    //         ->get()->getRow();

    //     if ($holiday) {
    //         return $this->response->setJSON([
    //             'status' => 203,
    //             'message' => 'No slots available on this date as it is a holiday.'
    //         ])->setStatusCode(200);
    //     }

    //     // 2. Fetch slots
    //     $slots = $this->db->table('tbl_slots')
    //         ->where([
    //             'branch_id' => $branch_id,
    //             'day_name' => $day_name,
    //             'is_deleted' => 'N'
    //         ])
    //         ->get()->getResult();

    //     // 3. Fetch chairs/beds and group by type
    //     $chairs_query = $this->db->table('tbl_chairs')
    //         ->where([
    //             'branch_id' => $branch_id,
    //             'is_active' => 'Y',
    //             'is_deleted' => 'N'
    //         ])
    //         ->get()->getResult();

    //     $ref_to_chair_map = []; // ref_id => ['chair_id' => 'C1', 'type' => 'Chair']
    //     $type_to_chairs = [];   // Chair => ['C1', 'C2'], Bed => ['B1', 'B2']

    //     foreach ($chairs_query as $chair) {
    //         $ref_to_chair_map[$chair->id] = [
    //             'chair_id' => $chair->chair_id,
    //             'type' => $chair->type
    //         ];

    //         if (!isset($type_to_chairs[$chair->type])) {
    //             $type_to_chairs[$chair->type] = [];
    //         }

    //         $type_to_chairs[$chair->type][] = $chair->chair_id;
    //     }

    //     // 4. Fetch booked chairs/beds
    //     $booked_slots = $this->db->table('tbl_booked_slots')
    //         ->where([
    //             'branch_id' => $branch_id,
    //             'selected_date' => $selected_date,
    //             'is_deleted' => 'N'
    //         ])
    //         ->get()->getResult();

    //         print_r(($booked_slots));die;

    //     // 5. Organize booked chairs per slot
    //     $booked_slot_info = []; // slot_id => type => booked_chairs

    //     foreach ($booked_slots as $bs) {
    //         $slot_id = $bs->slots_id;
    //         $ref_id = $bs->chair_ref_id;

    //         if (isset($ref_to_chair_map[$ref_id])) {
    //             $chair_id = $ref_to_chair_map[$ref_id]['chair_id'];
    //             $type = $ref_to_chair_map[$ref_id]['type'];

    //             if (!isset($booked_slot_info[$slot_id][$type])) {
    //                 $booked_slot_info[$slot_id][$type] = [];
    //             }

    //             $booked_slot_info[$slot_id][$type][] = $chair_id;
    //         }
    //     }

    //     // 6. Final slot processing
    //     $final_slots = [];

    //     foreach ($slots as $slot) {
    //         $slot_time = DateTime::createFromFormat('g:i A', $slot->slots_time, new DateTimeZone('Asia/Kolkata'));
    //         if ($is_today && $slot_time <= $current_time) {
    //             continue;
    //         }

    //         $slot_details = [
    //             'id' => $slot->id,
    //             'slots_time' => $slot->slots_time,
    //             'day_name' => $slot->day_name,
    //             'branch_id' => $slot->branch_id,
    //             'types' => []  // Holds chair/bed data by type
    //         ];

    //         foreach ($type_to_chairs as $type => $all_chairs_of_type) {
    //             $booked = $booked_slot_info[$slot->id][$type] ?? [];
    //             $available = array_values(array_diff($all_chairs_of_type, $booked));

    //             if (count($available) > 0) {
    //                 $slot_details['types'][] = [
    //                     'type' => $type,
    //                     'remaining_count' => count($available),
    //                     'available_ids' => $available,
    //                     'booked_ids' => $booked
    //                 ];
    //             }
    //         }

    //         if (!empty($slot_details['types'])) {
    //             $final_slots[] = $slot_details;
    //         }
    //     }

    //     return $this->response->setJSON([
    //         'status' => 200,
    //         'data' => $final_slots,
    //         'total_types' => $type_to_chairs // summary of all chairs/beds
    //     ])->setStatusCode(200);
    // }

    // private function getChairAvailabilityMap(array $booked_slots, array $chairs_query): array
    // {
    //     print_r($booked_slots);
    //     print_r($chairs_query);

    //     $availability_map = []; // ref_id => earliest available time

    //     // Loop through active chairs
    //     foreach ($chairs_query as $chair) {
    //         $ref_id = $chair->id;
    //         $earliest_time = null;

    //         // Loop through booked slots to find matching chair_ref_id
    //         foreach ($booked_slots as $slot) {
    //             if ($slot->chair_ref_id == $ref_id && $slot->chair_available_from) {
    //                 $current_time = DateTime::createFromFormat('H:i:s', $slot->chair_available_from, new DateTimeZone('Asia/Kolkata'));

    //                 if (!$earliest_time || $current_time < $earliest_time) {
    //                     $earliest_time = $current_time;
    //                 }
    //             }
    //         }

    //         // Save only if a time was found
    //         if ($earliest_time) {
    //             $availability_map[$ref_id] = $earliest_time->format('H:i:s');
    //         }
    //     }

    //     return $availability_map;
    // }


    // public function fetchslots()
    // {
    //     $rawInput = file_get_contents('php://input');
    //     $input = json_decode($rawInput, true);
    //     $selected_date = $input['selected_date'];
    //     $branch_id = $input['branch_id'];
    //     $day_name = DateTime::createFromFormat('Y-m-d', $selected_date)->format('l');
    //     $timezone = new DateTimeZone('Asia/Kolkata');
    //     $current_time = new DateTime('now', $timezone);
    //     $current_date = $current_time->format('Y-m-d');
    //     $is_today = ($selected_date === $current_date);

    //     // 1. Check for holiday
    //     $holiday = $this->db->table('tbl_holiday')
    //         ->where(['date' => $selected_date, 'is_deleted' => 'N'])
    //         ->get()->getRow();

    //     if ($holiday) {
    //         return $this->response->setJSON([
    //             'status' => 203,
    //             'message' => 'No slots available on this date as it is a holiday.'
    //         ])->setStatusCode(200);
    //     }

    //     // 2. Fetch slots
    //     $slots = $this->db->table('tbl_slots')
    //         ->where([
    //             'branch_id' => $branch_id,
    //             'day_name' => $day_name,
    //             'is_deleted' => 'N'
    //         ])
    //         ->get()->getResult();

    //     // 3. Fetch chairs/beds and group by type
    //     $chairs_query = $this->db->table('tbl_chairs')
    //         ->where([
    //             'branch_id' => $branch_id,
    //             'is_active' => 'Y',
    //             'type' => 'chair'
    //         ])
    //         ->get()->getResult();
    //     // print_r($chairs_query);die;

    //     $ref_to_chair_map = []; // ref_id => ['chair_id' => 'C1', 'type' => 'Chair']
    //     $type_to_chairs = [];   // Chair => ['C1', 'C2'], Bed => ['B1', 'B2']

    //     foreach ($chairs_query as $chair) {
    //         $ref_to_chair_map[$chair->id] = [
    //             'chair_id' => $chair->chair_id,
    //             'type' => $chair->type
    //         ];
    //         // print_r($ref_to_chair_map);die;

    //         if (!isset($type_to_chairs[$chair->type])) {
    //             $type_to_chairs[$chair->type] = [];
    //         }

    //         $type_to_chairs[$chair->type][] = $chair->chair_id;
    //     }
    //     // print_r( $type_to_chairs);die;

    //     // 4. Fetch booked chairs/beds
    //     $booked_slots = $this->db->table('tbl_booked_slots')
    //         ->where([
    //             'branch_id' => $branch_id,
    //             'selected_date' => $selected_date,
    //             'is_deleted' => 'N'
    //         ])
    //         ->get()->getResult();

    //     // print_r( $booked_slots);die;

    //     $chair_availability_map = $this->getChairAvailabilityMap($booked_slots, $chairs_query);

    //     print_r($chair_availability_map);
    //     die;
    //     // // 5. Organize booked chairs per slot
    //     // $booked_slot_info = []; // slot_id => type => booked_chairs
    //     // $booked_tracker = [];   // Avoid duplicate bookings for same chair in same slot

    //     // foreach ($booked_slots as $bs) {
    //     //     $slot_id = $bs->slots_id;
    //     //     $ref_id = $bs->chair_ref_id;

    //     //     if (!isset($ref_to_chair_map[$ref_id])) continue;

    //     //     $chair_id = $ref_to_chair_map[$ref_id]['chair_id'];
    //     //     $type = $ref_to_chair_map[$ref_id]['type'];

    //     //     // Prevent double booking
    //     //     if (!isset($booked_tracker[$slot_id])) {
    //     //         $booked_tracker[$slot_id] = [];
    //     //     }
    //     //     if (in_array($chair_id, $booked_tracker[$slot_id])) {
    //     //         continue;
    //     //     }
    //     //     $booked_tracker[$slot_id][] = $chair_id;

    //     //     if (!isset($booked_slot_info[$slot_id][$type])) {
    //     //         $booked_slot_info[$slot_id][$type] = [];
    //     //     }

    //     //     $booked_slot_info[$slot_id][$type][] = $chair_id;
    //     // }

    //     // // print_r($booked_slot_info);exit();

    //     // // Find the earliest available time across all booked chairs
    //     // $earliest_chair_availability = null;
    //     // $earliest_chair_ref_id = null;

    //     // foreach ($booked_slots as $bs) {
    //     //     $available_time = $bs->chair_available_from;
    //     //     // print_r($available_time);

    //     //     if ($available_time) {
    //     //         // Convert to Asia/Kolkata timezone
    //     //         $dt = DateTime::createFromFormat('H:i:s', $available_time, new DateTimeZone('Asia/Kolkata'));

    //     //         // Max of earliest available time
    //     //         if (!$earliest_chair_availability || $dt < $earliest_chair_availability) {
    //     //             $earliest_chair_availability = $dt;
    //     //             $earliest_chair_ref_id = $bs->chair_ref_id;
    //     //         }
    //     //     }
    //     // }

    //     // // print_r($earliest_chair_availability);exit();

    //     // // 6. Final slot processing
    //     // $is_first_eligible_slot = true;
    //     // // echo "<pre>Slots in 6th step :";
    //     // // print_r($slots);
    //     // // echo'</pre>';
    //     // foreach ($slots as $slot) {
    //     //     $slot_time = DateTime::createFromFormat('g:i A', $slot->slots_time, new DateTimeZone('Asia/Kolkata'));

    //     //     $slot_details = [
    //     //         'id' => $slot->id,
    //     //         'slots_time' => $slot->slots_time,
    //     //         'day_name' => $slot->day_name,
    //     //         'branch_id' => $slot->branch_id,
    //     //         'types' => [],
    //     //         'total_available_count' => 0
    //     //     ];

    //     //     foreach ($type_to_chairs as $type => $all_chairs_of_type) {
    //     //         $booked = $booked_slot_info[$slot->id][$type] ?? [];
    //     //         $available = array_values(array_diff($all_chairs_of_type, $booked));
    //     //         // print_r($booked); die;
    //     //         if (count($available) > 0) {
    //     //             $slot_details['types'][] = [
    //     //                 'type' => $type,
    //     //                 'remaining_count' => count($available),
    //     //                 'available_ids' => $available,
    //     //                 'booked_ids' => $booked
    //     //             ];
    //     //             $slot_details['total_available_count'] += count($available);
    //     //         }
    //     //     }
    //     // // print_r($booked); die;
    //     //     if (!empty($slot_details['types'])) {
    //     //         // Only add force chair ref if earliest chair is available for this slot
    //     //         if (
    //     //             $is_first_eligible_slot &&
    //     //             $earliest_chair_availability &&
    //     //             $slot_time >= $earliest_chair_availability
    //     //         ) {
    //     //             $slot_details['force_chair_ref_id'] = $earliest_chair_ref_id;
    //     //             $is_first_eligible_slot = false;
    //     //         }

    //     //         $final_slots[] = $slot_details;
    //     //     }
    //     // }

    //     // // echo '<pre>';print_r($final_slots);die;

    //     // return $this->response->setJSON([
    //     //     'status' => 200,
    //     //     'data' => $final_slots,
    //     //     'total_types' => $type_to_chairs
    //     // ])->setStatusCode(200);

    //     $minTime = null;
    //     $earliest_chair_ref_id = null;

    //     foreach ($chair_availability_map as $ref_id => $time) {
    //         if ($time) {
    //             $dt = DateTime::createFromFormat('H:i:s', $time, new DateTimeZone('Asia/Kolkata'));
    //             if (!$minTime || $dt < $minTime) {
    //                 $minTime = $dt;
    //                 $earliest_chair_ref_id = $ref_id;
    //             }
    //         }
    //     }

    //     // No fallback. If no available chair time, skip assigning
    //     $is_first_eligible_slot = true;
    //     $final_slots = [];

    //     foreach ($slots as $slot) {
    //         $slot_time = DateTime::createFromFormat('g:i A', $slot->slots_time, new DateTimeZone('Asia/Kolkata'));

    //         // Only continue if slot is at or after the earliest available time
    //         if ($minTime && $slot_time < $minTime) {
    //             continue;
    //         }

    //         $slot_details = [
    //             'id' => $slot->id,
    //             'slots_time' => $slot->slots_time,
    //             'day_name' => $slot->day_name,
    //             'branch_id' => $slot->branch_id,
    //             'types' => [],
    //             'total_available_count' => 0
    //         ];

    //         foreach ($type_to_chairs as $type => $all_chairs_of_type) {
    //             $booked = $booked_slot_info[$slot->id][$type] ?? [];
    //             $available = array_values(array_diff($all_chairs_of_type, $booked));

    //             if (count($available) > 0) {
    //                 $slot_details['types'][] = [
    //                     'type' => $type,
    //                     'remaining_count' => count($available),
    //                     'available_ids' => $available,
    //                     'booked_ids' => $booked
    //                 ];
    //                 $slot_details['total_available_count'] += count($available);
    //             }
    //         }

    //         if (!empty($slot_details['types'])) {
    //             if ($is_first_eligible_slot && $earliest_chair_ref_id) {
    //                 $slot_details['force_chair_ref_id'] = $earliest_chair_ref_id;
    //                 $is_first_eligible_slot = false;
    //             }

    //             $final_slots[] = $slot_details;
    //         }
    //     }
    //     // print_r($final_slots);die;
    //     return $this->response->setJSON([
    //         'status' => 200,
    //         'data' => $final_slots
    //     ])->setStatusCode(200);
    // }

    private function getChairAvailabilityMap(array $booked_slots, array $chairs_query, int $total_duration, array $available_slots): array
    {
        $availability_map = []; // ref_id => data
        $timezone = new DateTimeZone('Asia/Kolkata');
    
        // Group bookings by chair_ref_id
        $grouped_by_chair = [];
    
        foreach ($booked_slots as $slot) {
            $ref_id = $slot->chair_ref_id;
            if (!isset($grouped_by_chair[$ref_id])) {
                $grouped_by_chair[$ref_id] = [];
            }
            $grouped_by_chair[$ref_id][] = $slot;
        }
    
        foreach ($chairs_query as $chair) {
            $ref_id = $chair->id;
            $bookings = $grouped_by_chair[$ref_id] ?? [];

            // print_r( $bookings);
    
            // Sort by slots_time
            usort($bookings, function ($a, $b) {
                $a_time = DateTime::createFromFormat('h:i A', $a->slot_time, new DateTimeZone('Asia/Kolkata'));
                $b_time = DateTime::createFromFormat('h:i A', $b->slot_time, new DateTimeZone('Asia/Kolkata'));
                return $a_time <=> $b_time;
            });
    
            $latest_time = null;
            $gap_slot_info = null;
    
            for ($i = 1; $i < count($bookings); $i++) {
                $prev_available = DateTime::createFromFormat('H:i:s', $bookings[$i - 1]->chair_available_from, $timezone);
                $next_slot_time = DateTime::createFromFormat('h:i A', $bookings[$i]->slot_time, $timezone);
            
                if ($prev_available && $next_slot_time) {
                    $gap_minutes = ($next_slot_time->getTimestamp() - $prev_available->getTimestamp()) / 60;
            
                    // 👇 Debug output
                    // echo "Chair ID: $ref_id | Gap between " . $prev_available->format('H:i:s') . " and " . $next_slot_time->format('H:i:s') . " is $gap_minutes minutes\n";
            
                    if ($gap_minutes >= $total_duration) {
                        $gap_slot_info = [
                            'start' => $prev_available->format('H:i:s'),
                            'end' => $next_slot_time->format('H:i:s'),
                            'gap' => $gap_minutes
                        ];
                        break;
                    }
                }
            }
            
    
            // Use last available time from latest booking if available
           
            if (count($bookings) > 0) {
                $last_booking = end($bookings);
                $latest_time = $last_booking->chair_available_from;
            } else {
                // No bookings for this chair — get earliest slot for the day
                if (!empty($available_slots)) {
                    // Assuming slots are already sorted, else sort them first
                    $first_slot = $available_slots[0];
                    $slot_time_obj = DateTime::createFromFormat('g:i A', $first_slot->slots_time, $timezone);
                    $latest_time = $slot_time_obj ? $slot_time_obj->format('H:i:s') : null;
                }
            }
            
    
            // Store data
            $availability_map[$ref_id] = [
                'latest_time' => $latest_time,
                'gap_available' => $gap_slot_info // may be null if no suitable gap
            ];
        }
    
        // print_r($availability_map);exit();
        return $availability_map;
    }
    
    



//     public function fetchslots()
// {
//     $rawInput = file_get_contents('php://input');
//     $input = json_decode($rawInput, true);
//     // print_r($input);exit();
//     $selected_date = $input['selected_date'];
//     $branch_id = $input['branch_id'];
//     $day_name = DateTime::createFromFormat('Y-m-d', $selected_date)->format('l');
//     $timezone = new DateTimeZone('Asia/Kolkata');
//     $current_time = new DateTime('now', $timezone);
//     $current_date = $current_time->format('Y-m-d');
//     $is_today = ($selected_date === $current_date);

//     // 1. Check for holiday
//     $holiday = $this->db->table('tbl_holiday')
//         ->where(['date' => $selected_date, 'is_deleted' => 'N'])
//         ->get()->getRow();

//     if ($holiday) {
//         return $this->response->setJSON([
//             'status' => 203,
//             'message' => 'No slots available on this date as it is a holiday.'
//         ])->setStatusCode(200);
//     }

//     // 2. Fetch slots
//     $slots = $this->db->table('tbl_slots')
//         ->where([
//             'branch_id' => $branch_id,
//             'day_name' => $day_name,
//             'is_deleted' => 'N'
//         ])
//         ->get()->getResult();

//     // 3. Fetch chairs/beds and group by type
//     $chairs_query = $this->db->table('tbl_chairs')
//         ->where([
//             'branch_id' => $branch_id,
//             'is_active' => 'Y',
//             'type' => 'chair'
//         ])
//         ->get()->getResult();

//     $ref_to_chair_map = [];
//     $type_to_chairs = [];

//     foreach ($chairs_query as $chair) {
//         $ref_to_chair_map[$chair->id] = [
//             'chair_id' => $chair->chair_id,
//             'type' => $chair->type
//         ];
//         if (!isset($type_to_chairs[$chair->type])) {
//             $type_to_chairs[$chair->type] = [];
//         }
//         $type_to_chairs[$chair->type][] = $chair->chair_id;
//     }

//     // 4. Fetch booked slots
//     $booked_slots = $this->db->table('tbl_booked_slots')
//         ->where([
//             'branch_id' => $branch_id,
//             'selected_date' => $selected_date,
//             'is_deleted' => 'N'
//         ])
//         ->get()->getResult();
        
//         // echo'<pre>';print_r($booked_slots);
//         //  echo'<pre>';print_r($chairs_query);
//         //  exit();
//     // Get availability map
//     $chair_availability_map = $this->getChairAvailabilityMap($booked_slots, $chairs_query, $input['total_duration'], $slots);



// // print_r($chair_availability_map);exit();

//     // === NEW: Find minimum available time and corresponding chair_ref_id ===
 
//     $min_time = null;
//     $min_chair_ref_id = null;
    
//     // If no bookings exist, fallback to first chair in branch
//     if (empty($chair_availability_map)) {
//         if (!empty($chairs_query)) {
//             $min_chair_ref_id = $chairs_query[0]->id;
//         }
//     } else {
//         foreach ($chair_availability_map as $ref_id => $time_data) {
//             if (isset($time_data['latest_time']) && $time_data['latest_time']) {
//                 $time_obj = DateTime::createFromFormat('H:i:s', $time_data['latest_time'], new DateTimeZone('Asia/Kolkata'));
    
//                 if (!$min_time || $time_obj < $min_time) {
//                     $min_time = $time_obj;
//                     $min_chair_ref_id = $ref_id;
//                 }
//             }
//         }
    
//         // Extra fallback if no latest_time available
//         if (!$min_chair_ref_id && !empty($chairs_query)) {
//             $min_chair_ref_id = $chairs_query[0]->id;
//         }
//     }
    
//     // Assign final chair_ref_id
//     $input['chair_ref_id'] = $min_chair_ref_id;
    
    

//     $min_available_time = $min_time ? $min_time->format('H:i:s') : null;

//     // print_r($min_available_time);exit();

//     // === Filter eligible slots after min_time ===
//     $filtered_slots = [];
//     $booked_slot_info = []; // Ensure this is initialized

//     foreach ($slots as $slot) {
//         $slot_time = DateTime::createFromFormat('g:i A', $slot->slots_time, new DateTimeZone('Asia/Kolkata'));

//         // Skip slots before the min_time
//         if ($min_time && $slot_time < $min_time) {
//             continue;
//         }

//         $slot_details = [
//             'id' => $slot->id,
//             'slots_time' => $slot->slots_time,
//             'day_name' => $slot->day_name,
//             'branch_id' => $slot->branch_id,
//             'types' => [],
//             'total_available_count' => 0
//         ];

//         foreach ($type_to_chairs as $type => $all_chairs_of_type) {
//             $booked = $booked_slot_info[$slot->id][$type] ?? [];
//             $available = array_values(array_diff($all_chairs_of_type, $booked));

//             if (count($available) > 0) {
//                 $slot_details['types'][] = [
//                     'type' => $type,
//                     'remaining_count' => count($available),
//                     'available_ids' => $available,
//                     'booked_ids' => $booked
//                 ];
//                 $slot_details['total_available_count'] += count($available);
//             }
//         }

//         if (!empty($slot_details['types'])) {
//             $filtered_slots[] = $slot_details;
//         }
//     }


//     // print_r($filtered_slots);exit();
//     // print_r($min_available_time);
//     // print_r($min_chair_ref_id);exit();
//     return $this->response->setJSON([
//         'status' => 200,
//         'slots' => $filtered_slots,
//         'min_available_time' => $min_available_time,
//         'min_chair_ref_id' => $min_chair_ref_id
//     ])->setStatusCode(200);
// }


public function fetchslots()
{
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    $selected_date = $input['selected_date'];
    $branch_id = $input['branch_id'];

    $day_name = DateTime::createFromFormat('Y-m-d', $selected_date)->format('l');
    $timezone = new DateTimeZone('Asia/Kolkata');
    $current_time = new DateTime('now', $timezone);
    $current_date = $current_time->format('Y-m-d');
    $is_today = ($selected_date === $current_date);

    // 1. Check for holidays
    $holiday = $this->db->table('tbl_holiday')
        ->where(['date' => $selected_date, 'is_deleted' => 'N'])
        ->get()->getRow();

    if ($holiday) {
        return $this->response->setJSON([
            'status' => 203,
            'message' => 'No slots available on this date as it is a holiday.'
        ])->setStatusCode(200);
    }

    // 2. Fetch active chairs in the branch (excluding beds)
    $chairs = $this->db->table('tbl_chairs')
        ->select('id')
        ->where([
            'branch_id' => $branch_id,
            'type' => 'chair',
            'is_active' => 'Y',
            'is_deleted' => 'N'
        ])
        ->get()->getResultArray();

    $activeChairIds = array_column($chairs, 'id');
    $totalActiveChairs = count($activeChairIds);

    // 3. Get all slot bookings for the selected date
    $booked = $this->db->table('tbl_booked_slots')
        ->select('chair_ref_id, booked_slot_ids')
        ->where([
            'selected_date' => $selected_date,
            'branch_id' => $branch_id,
            'is_deleted' => 'N'
        ])
        ->get()->getResult();

    // 4. Map of slot_id => array of booked chair IDs
    $slotChairMap = [];

    foreach ($booked as $booking) {
        $slot_ids = is_string($booking->booked_slot_ids)
            ? json_decode($booking->booked_slot_ids, true)
            : $booking->booked_slot_ids;

        foreach ($slot_ids as $slot_id) {
            if (!isset($slotChairMap[$slot_id])) {
                $slotChairMap[$slot_id] = [];
            }
            $slotChairMap[$slot_id][] = (int)$booking->chair_ref_id;
        }
    }

    // 5. Fetch all available slots for that day and branch
    $slots = $this->db->table('tbl_slots')
        ->where([
            'branch_id' => $branch_id,
            'day_name' => $day_name,
            'is_deleted' => 'N'
        ])
        ->orderBy('slots_time', 'ASC')
        ->get()->getResult();

    // 6. Filter and format slots
    $responseSlots = [];

    foreach ($slots as $slot) {
        $slotId = $slot->id;
        $bookedChairsForSlot = isset($slotChairMap[$slotId]) ? array_unique($slotChairMap[$slotId]) : [];

        $availableChairs = array_diff($activeChairIds, $bookedChairsForSlot);

        if (empty($availableChairs)) {
            continue; // No chairs available, skip slot
        }

        $responseSlots[] = [
            'id' => $slotId,
            'slot_time' => $slot->slots_time,
            'is_booked' => !empty($bookedChairsForSlot),
            'chair_ref_ids' => $bookedChairsForSlot,
            'available_chairs_count' => count($availableChairs)
        ];
    }

    return $this->response->setJSON([
        'status' => 200,
        'slots' => $responseSlots
    ]);
}












    public function getsections()
    {
        try {
            $builder = $this->db->table('tbl_register')
                ->select('tbl_register.id as con_id, tbl_register.degree, tbl_register.role_ref_code, tbl_register.name, tbl_register.section,tbl_register.mobile,tbl_register.password, tbl_section.section_name') // Selecting the section_name
                ->join('tbl_section', 'tbl_section.id = tbl_register.section', 'left') // Join to get section_name
                ->where('tbl_register.is_deleted', 'N')
                ->where('tbl_register.role_ref_code', 'Consultant');


            $result = $builder->get()->getResultArray();

            if ($result) {
                return $this->response->setJSON([
                    'status' => 200,
                    'message' => 'Records retrieved successfully',
                    'data' => $result
                ])->setStatusCode(200);
            }

            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No records found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }

    public function getempy()
    {
        try {
            $builder = $this->db->table('tbl_register')
                ->select('tbl_register.*, tbl_section.section_name') // Selecting the section_name
                ->join('tbl_section', 'tbl_section.id = tbl_register.section', 'left') // Join to get section_name
                ->where('tbl_register.is_deleted', 'N')
                ->groupStart() // Start grouping conditions
                ->where('tbl_register.role_ref_code', 'Employee')
                ->orWhere('tbl_register.role_ref_code', 'Admin')
                ->groupEnd(); // End grouping conditions

            $result = $builder->get()->getResultArray();

            if ($result) {
                return $this->response->setJSON([
                    'status' => 200,
                    'message' => 'Records retrieved successfully',
                    'data' => $result
                ])->setStatusCode(200);
            }

            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No records found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }


    public function getslots($table)
    {
        $consultantId = $this->request->getPost('consultantId') ?? $this->request->getJSON()->consultantId;
        $branchId = $this->request->getPost('branchId') ?? $this->request->getJSON()->branchId;

        try {
            $db = \Config\Database::connect();

            // Using a direct query with JOIN
            $sql = "
            SELECT s.*, sd.slots_time, sd.id as slot_id, sd.is_deleted as slotis_deleted
            FROM $table s
            LEFT JOIN tbl_slots sd ON s.id = sd.schedule_id
            WHERE s.doctor_id = ? AND s.branch_id = ?";

            $query = $db->query($sql, [$consultantId, $branchId]);
            $slots = $query->getResultArray();

            if ($slots) {
                return $this->response->setJSON([
                    'status' => 200,
                    'message' => 'Slots retrieved successfully',
                    'data' => $slots, // Return all slots without filtering
                ])->setStatusCode(200);
            }

            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No slots found',
                'data' => [],
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => [],
            ])->setStatusCode(500);
        }
    }




    public function getFilteredAppointments($table)
    {
        $consultantId = $this->request->getPost('consultantId') ?? $this->request->getJSON()->consultantId;
        $branchId = $this->request->getPost('branchId') ?? $this->request->getJSON()->branchId;
        $tab = $this->request->getPost('tab') ?? $this->request->getJSON()->tab;

        log_message('info', 'Fetching appointments with parameters: consultantId={consultantId}, branchId={branchId}, tab={tab}', [
            'consultantId' => $consultantId,
            'branchId' => $branchId,
            'tab' => $tab
        ]);

        try {
            $builder = $this->db->table($table)
                // ->select("$table.*, tbl_branch.branch_name, tbl_register.name as consultant_name")
                // ->where("$table.is_deleted", 'N')
                // ->join('tbl_branch', "tbl_branch.id = $table.branch", 'left')
                // ->join('tbl_register', "tbl_register.id = $table.consultant", 'left');

                ->select("$table.*, tbl_branch.branch_name, tbl_register.name as consultant_name, appointment_status, tbl_section.section_name, tbl_slots.slots_time")
                ->where("$table.is_deleted", 'N')
                ->join('tbl_branch', "tbl_branch.id = $table.branch", 'left')
                ->join('tbl_section', "$table.section = tbl_section.id", 'left')
                ->join('tbl_slots', "CAST($table.slot AS INTEGER) = tbl_slots.id", 'left')
                ->join('tbl_register', "tbl_register.id = $table.consultant", 'left');

            // Filter by consultant ID with special handling for "Exercise" consultant
            if ($consultantId) {
                $consultantQuery = $this->db->table('tbl_register')
                    ->select('name')
                    ->where('id', intval($consultantId))
                    ->get()
                    ->getRow();

                $consultantName = $consultantQuery ? $consultantQuery->name : null;
                log_message('info', 'Consultant Name: {consultantName}', ['consultantName' => $consultantName]);

                if ($consultantName === 'Exercise') {
                    $builder->where("$table.consultant", null);
                } else {
                    $builder->where("$table.consultant", intval($consultantId));
                }
            }

            // Filter by branch ID
            if ($branchId) {
                $builder->where("$table.branch", intval($branchId));
            }

            // Filter by tab name
            $currentDate = date('Y-m-d');
            switch ($tab) {
                case 'home':
                    // Home tab: include status (PE) with today's date
                    $builder->Where('appointment_status', 'PE')
                        ->where("$table.dates", $currentDate); // Filter by today's date
                    break;

                case 'upcoming':
                    // Upcoming tab: pending appointments from today onwards
                    $builder->where('appointment_status', 'PE')
                        ->where("$table.dates >=", $currentDate);
                    break;

                case 'cancelled':
                    // Cancelled tab: only cancelled appointments
                    $builder->where('appointment_status', 'CN');
                    break;

                case 'conducted':
                    // Conducted tab: only conducted appointments
                    $builder->where('appointment_status', 'CD');
                    break;

                case 'pending':
                    // Conducted tab: only conducted appointments
                    $builder->where('appointment_status', 'PE');
                    break;



                default:
                    // Default to empty results if tab name is invalid
                    $builder->where('appointment_status', 'INVALID');
            }

            // Execute query and fetch results
            $result = $builder->get()->getResultArray();

            log_message('info', 'Query Result: {result}', ['result' => json_encode($result)]);

            // Return the results in JSON format
            if ($result) {
                return $this->response->setJSON([
                    'status' => 200,
                    'message' => 'Appointments retrieved successfully',
                    'data' => $result
                ])->setStatusCode(200);
            }

            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No appointments found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            log_message('error', 'An error occurred in getFilteredAppointments: {message}', ['message' => $e->getMessage()]);

            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }

    public function getFilteredAppointmentstodays($table)
    {
        $consultantId = $this->request->getPost('consultantId') ?? $this->request->getJSON()->consultantId;
        $branchId = $this->request->getPost('branchId') ?? $this->request->getJSON()->branchId;
        $tab = $this->request->getPost('tab') ?? $this->request->getJSON()->tab;
        $currentDate = date('Y-m-d');

        log_message('info', 'Fetching appointments with parameters: consultantId={consultantId}, branchId={branchId}, tab={tab}', [
            'consultantId' => $consultantId,
            'branchId' => $branchId,
            'tab' => $tab
        ]);

        try {
            $builder = $this->db->table($table)
                // ->select("$table.*, tbl_branch.branch_name, tbl_register.name as consultant_name")
                // ->where("$table.is_deleted", 'N')
                // ->join('tbl_branch', "tbl_branch.id = $table.branch", 'left')
                // ->join('tbl_register', "tbl_register.id = $table.consultant", 'left');



                ->select("$table.*, tbl_branch.branch_name, tbl_register.name as consultant_name, appointment_status, tbl_section.section_name, tbl_slots.slots_time")
                ->where("$table.is_deleted", 'N')
                ->join('tbl_branch', "tbl_branch.id = $table.branch", 'left')
                ->join('tbl_section', "$table.section = tbl_section.id", 'left')
                ->join('tbl_slots', "CAST($table.slot AS INTEGER) = tbl_slots.id", 'left')
                ->join('tbl_register', "tbl_register.id = $table.consultant", 'left');


            // Filter by consultant ID with special handling for "Exercise" consultant
            if ($consultantId) {
                $consultantQuery = $this->db->table('tbl_register')
                    ->select('name')
                    ->where('id', intval($consultantId))
                    ->get()
                    ->getRow();

                $consultantName = $consultantQuery ? $consultantQuery->name : null;
                log_message('info', 'Consultant Name: {consultantName}', ['consultantName' => $consultantName]);

                if ($consultantName === 'Exercise') {
                    $builder->where("$table.consultant", null);
                } else {
                    $builder->where("$table.consultant", intval($consultantId));
                }
            }

            // Filter by branch ID
            if ($branchId) {
                $builder->where("$table.branch", intval($branchId));
            }

            // Filter by tab name
            $currentDate = date('Y-m-d');
            switch ($tab) {
                case 'home':
                    // Home tab: include status (PE) with today's date
                    $builder->Where('appointment_status', 'PE')
                        ->where("$table.dates", $currentDate); // Filter by today's date
                    break;

                case 'upcoming':
                    // Upcoming tab: pending appointments from today onwards
                    $builder->where('appointment_status', 'PE')
                        ->where("$table.dates >=", $currentDate);
                    break;

                case 'cancelled':
                    // Cancelled tab: only cancelled appointments
                    $builder->where('appointment_status', 'CN')
                        ->where("DATE($table.dates)", $currentDate); // Ensure only the date part is compared

                    break;

                case 'conducted':
                    // Conducted tab: only conducted appointments
                    $builder->where('appointment_status', 'CD')
                        ->where("DATE($table.dates)", $currentDate); // Ensure only the date part is compared

                    break;

                case 'pending':
                    // Conducted tab: only conducted appointments
                    $builder->where('appointment_status', 'PE');
                    break;

                default:
                    // Default to empty results if tab name is invalid
                    $builder->where('appointment_status', 'INVALID');
            }

            // Execute query and fetch results
            $result = $builder->get()->getResultArray();

            log_message('info', 'Query Result: {result}', ['result' => json_encode($result)]);

            // Return the results in JSON format
            if ($result) {
                return $this->response->setJSON([
                    'status' => 200,
                    'message' => 'Appointments retrieved successfully',
                    'data' => $result
                ])->setStatusCode(200);
            }

            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No appointments found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            log_message('error', 'An error occurred in getFilteredAppointments: {message}', ['message' => $e->getMessage()]);

            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }

    public function getFilteredReport($table)
    {
        $consultantId = $this->request->getPost('consultantId') ?? $this->request->getJSON()->consultantId;
        $branchId = $this->request->getPost('branchId') ?? $this->request->getJSON()->branchId;
        $tab = $this->request->getPost('tab') ?? $this->request->getJSON()->tab;
        $date = $this->request->getPost('date') ?? $this->request->getJSON()->date;
        $serviceId = $this->request->getPost('serviceId') ?? $this->request->getJSON()->serviceId;

        // New search parameters
        $mobileNo = $this->request->getPost('mobileNo') ?? $this->request->getJSON()->mobileNo;
        $clientName = $this->request->getPost('clientName') ?? $this->request->getJSON()->clientName;

        log_message('info', 'Fetching appointments with parameters: consultantId={consultantId}, branchId={branchId}, tab={tab}, date={date}, serviceId={serviceId}, mobileNo={mobileNo}, clientName={clientName}', [
            'consultantId' => $consultantId,
            'branchId' => $branchId,
            'tab' => $tab,
            'date' => $date,
            'serviceId' => $serviceId,
            'mobileNo' => $mobileNo,
            'clientName' => $clientName
        ]);

        try {
            $builder = $this->db->table($table)
                ->select("$table.*, tbl_branch.branch_name, tbl_register.name as consultant_name, appointment_status, tbl_section.section_name, tbl_slots.slots_time")
                ->where("$table.is_deleted", 'N')
                ->join('tbl_branch', "tbl_branch.id = $table.branch", 'left')
                ->join('tbl_section', "$table.section = tbl_section.id", 'left')
                ->join('tbl_slots', "CAST($table.slot AS INTEGER) = tbl_slots.id", 'left')
                ->join('tbl_register', "tbl_register.id = $table.consultant", 'left');

            // Filter by consultant ID
            if ($consultantId) {
                $consultantQuery = $this->db->table('tbl_register')
                    ->select('name')
                    ->where('id', intval($consultantId))
                    ->get()
                    ->getRow();

                $consultantName = $consultantQuery ? $consultantQuery->name : null;
                log_message('info', 'Consultant Name: {consultantName}', ['consultantName' => $consultantName]);

                if ($consultantName === 'Exercise') {
                    $builder->where("$table.consultant", null);
                } else {
                    $builder->where("$table.consultant", intval($consultantId));
                }
            }

            // Filter by branch ID
            if ($branchId) {
                $builder->where("$table.branch", intval($branchId));
            }

            // Filter by date
            if ($date) {
                $builder->where("$table.dates", $date);
            }

            // Filter by service ID
            if ($serviceId) {
                $builder->where("$table.section", intval($serviceId));
            }

            // Filter by mobile number (from tbl_register)
            if ($mobileNo) {
                $builder->where("$table.mobile_no", $mobileNo);
            }

            // Filter by client name (from tbl_register)
            if ($clientName) {
                $builder->like("$table.full_name", $clientName); // Use LIKE for partial matching
            }

            // Filter by tab name
            $currentDate = date('Y-m-d');
            switch ($tab) {
                case 'home':
                    $builder->where('appointment_status', 'CD');
                    break;
                case 'notconducted':
                    $builder->where('appointment_status', 'PE');
                    break;
                case 'clients':
                    $builder->whereIn('appointment_status', ['PE', 'CN', 'CD']);
                    break;
                case 'upcoming':
                    $builder->where('appointment_status', 'PE')
                        ->where("$table.dates >=", $currentDate);
                    break;
                case 'cancelled':
                    $builder->where('appointment_status', 'CN');
                    break;
                case 'conducted':
                    $builder->where('appointment_status', 'CD');
                    break;
                default:
                    $builder->where('appointment_status', 'INVALID');
            }

            // Execute query and fetch results
            $result = $builder->get()->getResultArray();

            // Log the last executed SQL query
            $lastQuery = $this->db->getLastQuery();
            log_message('info', 'Last Executed Query: {lastQuery}', ['lastQuery' => $lastQuery]);

            // Log the result
            log_message('info', 'Query Result: {result}', ['result' => json_encode($result)]);

            // Return the results in JSON format
            if ($result) {
                return $this->response->setJSON([
                    'status' => 200,
                    'message' => 'Appointments retrieved successfully',
                    'data' => $result
                ])->setStatusCode(200);
            }

            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No appointments found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            log_message('error', 'An error occurred in getFilteredAppointments: {message}', ['message' => $e->getMessage()]);

            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }




    public function createemp()
    {
        $resumeDirectory = 'public/assets/resume/';
        $id = $this->uri->getSegment(3); // Retrieve the ID from the URL segment
        $id = !empty($id) ? $id : null;  // Convert empty ID to NULL for PostgreSQL
        $existingResume = null;
        $section = isset($_POST['section']) ? (int)$_POST['section'] : null;
        $password = null;

        // Retrieve existing data if updating
        if (!empty($id)) {
            $existingData = $this->db->table('tbl_register')->where('id', $id)->get()->getRowArray();
            $existingResume = $existingData['resume'] ?? null;

            // Only hash a new password if provided
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            }
        } else {
            // For new records, hash the provided password
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        }

        // Handle file upload
        $resumeFileName = $existingResume;
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
            $fileName = $_FILES['resume']['name'];
            $tempPath = $_FILES['resume']['tmp_name'];
            $targetPath = $resumeDirectory . $fileName;
            if (move_uploaded_file($tempPath, $targetPath)) {
                $resumeFileName = $fileName;
            } else {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Failed to upload the resume file.'
                ]);
            }
        }

        // Prepare dynamic SQL query
        $sql = sprintf(
            "CALL manage_employee(%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
            $id !== null ? $this->db->escape($id) : 'NULL',  // Pass NULL for new records
            $this->db->escape($_POST['name']),
            $this->db->escape($section),
            $this->db->escape($_POST['mobile']),
            $this->db->escape($_POST['email']),
            $this->db->escape($_POST['address']),
            $password !== null ? $this->db->escape($password) : 'NULL', // Pass NULL if no password is provided
            $this->db->escape($_POST['joiningDate']),
            $this->db->escape($_POST['role_ref_code']),
            $this->db->escape($_POST['accessLevels']),
            $this->db->escape($resumeFileName)
        );

        // Execute the query
        try {
            $this->db->query($sql);
            $message = $id ? 'Employee record updated successfully!' : 'Employee record and resume saved successfully!';
            return $this->response->setJSON([
                'status' => 'success',
                'message' => $message
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'An error occurred: ' . $e->getMessage()
            ]);
        }
    }



    public function subscribtionappointment()
    {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);

        if (empty($input['appointment_date']) || empty($input['branch_id'])) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'Required fields are missing.'
            ])->setStatusCode(400);
        }
        $selected_date = $input['appointment_date'];
        $date_object = DateTime::createFromFormat('Y-m-d', $selected_date);
        if (!$date_object) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'Invalid date format. Please provide the date in YYYY-MM-DD format.'
            ])->setStatusCode(400);
        }
        $selected_date_formatted = $date_object->format('Y-m-d');
        $day_name = $date_object->format('l');
        // $doctor_id = $input['doctor_id'];
        $branch_id = $input['branch_id'];

        $slots_builder = $this->db->table('tbl_exerciseslot');
        $slots_builder->where([
            'branch_id' => $branch_id,
            'day_name' => $day_name,
            'is_deleted' => 'N'
        ]);
        $slots = $slots_builder->get()->getResult();
        $booked_slots_builder = $this->db->table('tbl_exercisebookedslot');
        $booked_slots_builder->select('slots_id');
        $booked_slots_builder->where([
            'branch_id' => $branch_id,
            'selected_date' => $selected_date_formatted,
            'is_deleted' => 'N'
        ]);
        $booked_slots = $booked_slots_builder->get()->getResultArray();
        $booked_slots_ids = array_column($booked_slots, 'slots_id');
        if (!empty($booked_slots_ids)) {
            $slots = array_filter($slots, function ($slot) use ($booked_slots_ids) {
                return !in_array($slot->id, $booked_slots_ids);
            });
        }

        return $this->response->setJSON([
            'status' => 200,
            'data' => array_values($slots)
        ])->setStatusCode(200);
    }
    public function index()
    {
        try {
            $db = \Config\Database::connect();
            if ($db->connect()) {
                return $this->response->setJSON(['status' => 'success', 'heee' => 'done', 'message' => 'Database connection established successfully.']);

                // return $this->response->setJSON(['status' => 'success', 'message' => 'Database connection established successfully.']);
            }
        } catch (DatabaseException $e) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Failed to connect to the database: ' . $e->getMessage()]);
        }
        return $this->response->setJSON(['status' => 'error', 'message' => 'Unable to establish database connection.']);
    }

    public function createemployee()
    {

        $name = $this->request->getPost('name');
        $email = $this->request->getPost('email');
        $address = $this->request->getPost('address');
        $mobile = $this->request->getPost('mobile');
        $created_at = date('Y-m-d H:i:s');

        $db = \Config\Database::connect();

        $sql = "EXEC sp_registration_insert @name = ?, @email = ?, @address = ?, @mobile = ?, @created_at = ?";
        try {
            $db->query($sql, [$name, $email, $address, $mobile, $created_at]);
            return $this->response->setJSON(['message' => 'Employee created successfully'])->setStatusCode(201);
        } catch (\Exception $e) {
            return $this->response->setJSON(['error' => 'Failed to create employee: ' . $e->getMessage()])->setStatusCode(500);
        }
    }

    public function get_todays_appointment_data($table, $id = null)
    {
        // print_r($table);
        // print_r($id);die;
        try {
            $db = \Config\Database::connect();
            if ($id) {
                $query = $db->query("CALL get_todays_appointment_data(?, ?, ?)", [$table, $id, NULL]);
            } else {
                $query = $db->query("CALL get_todays_appointment_data(?, NULL, ?)", [$table, NULL]);
            }
            $result = $query->getRowArray();
            // print_r($result);die;
            if ($result && !empty($result['result_json'])) {
                $data = json_decode($result['result_json'], true);
                return $this->response->setJSON([
                    'status' => 200,
                    'data' => $data['data'],
                ])->setStatusCode(200);
            }
            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No records found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }

    public function slotsmanage()
    {
        // Retrieve raw input and decode it
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        // print_r($input);die;
        if (empty($input)) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'No data provided.'
            ])->setStatusCode(400);
        }

        if ($input['status'] === 'B') {
            // If status is 'B' (Booked/Off For Day)
            $data = [
                'doctor_id' => $input['consultantId'] ?? null,
                'status' => $input['status'],
                'branch_id' => $input['branchId'] ?? null,
                'selected_date' => $input['date'] ?? null,
                'slots_id' => $input['id'] ?? null
            ];

            $builder = $this->db->table('tbl_booked_slots');
            if ($builder->insert($data)) {
                return $this->response->setJSON([
                    'status' => 200,
                    'message' => 'Record created successfully'
                ])->setStatusCode(200);
            }
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'Failed to create record'
            ])->setStatusCode(500);
        } elseif ($input['status'] === 'D') {
            // If status is 'D' (Deactivate)
            $updateData = ['is_deleted' => 'Y'];
            $id = $input['id'] ?? null;

            if ($this->db->table('tbl_slots')->where('id', $id)->update($updateData)) {
                return $this->response->setJSON([
                    'status' => 200,
                    'message' => 'Record marked as deleted successfully'
                ])->setStatusCode(200);
            }
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'Failed to mark the record as deleted'
            ])->setStatusCode(500);
        } elseif ($input['status'] === 'A') {
            // If status is 'A' (Active)
            $updateData = ['is_deleted' => 'N'];
            $id = $input['id'] ?? null;

            if ($this->db->table('tbl_slots')->where('id', $id)->update($updateData)) {
                return $this->response->setJSON([
                    'status' => 200,
                    'message' => 'Record marked as active successfully'
                ])->setStatusCode(200);
            }
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'Failed to mark the record as active'
            ])->setStatusCode(500);
        }

        // If an invalid status is provided
        return $this->response->setJSON([
            'status' => 400,
            'message' => 'Invalid status value'
        ])->setStatusCode(400);
    }
    // public function getAppointmentsWithSlots()
    // {
    //     try {
    //         // Call the PostgreSQL procedure
    //         $result = $this->db->query("CALL get_appointments_with_slots();");

    //         // Fetch the result (which is returned as a result set)
    //         $appointments = $result->getResultArray();

    //         // Check if records were found
    //         if ($appointments) {
    //             return $this->response->setJSON([
    //                 'status' => 200,
    //                 'message' => 'Appointments with slot times retrieved successfully',
    //                 'data' => $appointments  // Use the result directly
    //             ])->setStatusCode(200);
    //         } else {
    //             return $this->response->setJSON([
    //                 'status' => 404,
    //                 'message' => 'No records found',
    //                 'data' => []
    //             ])->setStatusCode(404);
    //         }
    //     } catch (\Exception $e) {
    //         // Handle any exceptions
    //         return $this->response->setJSON([ 
    //             'status' => 500,
    //             'message' => 'An error occurred: ' . $e->getMessage(),
    //             'data' => []
    //         ])->setStatusCode(500);
    //     }
    // }
    public function getAppointmentsWithSlots()
    {
        try {
            // Set primary table and fields to select
            $primaryTable = 'tbl_appointment';
            $selectFields = 'tbl_appointment.*, 
                         tbl_slots.slots_time, 
                         tbl_register.name AS consultant_name, 
                         tbl_section.section_name,
                         tbl_branch.branch_name';

            // Define the join conditions
            $builder = $this->db->table($primaryTable)
                ->select($selectFields)
                ->join('tbl_slots', 'CAST(tbl_appointment.slot AS INTEGER) = tbl_slots.id', 'left') // Join tbl_slots with tbl_appointment
                ->join('tbl_register', 'tbl_appointment.consultant = tbl_register.id', 'left') // Join tbl_register for consultant name
                ->join('tbl_branch', 'tbl_appointment.branch = tbl_branch.id', 'left') // Join tbl_branch for branch name
                ->join('tbl_section', 'tbl_appointment.section = tbl_section.id', 'left') // Join tbl_section for section name
                ->where('tbl_appointment.is_deleted', 'N'); // Add any required conditions, e.g., filter for non-deleted records

            // Execute the query and get results
            $result = $builder->get()->getResultArray();

            // Check if records were found
            if ($result) {
                return $this->response->setJSON([
                    'status' => 200,
                    'message' => 'Appointments with slot times retrieved successfully',
                    'data' => $result
                ])->setStatusCode(200);
            } else {
                return $this->response->setJSON([
                    'status' => 404,
                    'message' => 'No records found',
                    'data' => []
                ])->setStatusCode(404);
            }
        } catch (\Exception $e) {
            // Handle any exceptions
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }
    public function getConductedAppointments()
    {
        try {
            $db = \Config\Database::connect();
            $query = $db->query("CALL getall_conducted_appointments(?)", [NULL]);
            $result = $query->getRowArray();
            if ($result && !empty($result['result_json'])) {
                $data = json_decode($result['result_json'], true);
                return $this->response->setJSON([
                    'status' => 200,
                    'data' => $data['data'],
                ])->setStatusCode(200);
            }
            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No records found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }
    public function get_consultantwise_appointments()
    {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        $id = $input['userId'];
        try {
            $db = \Config\Database::connect();
            if ($id) {
                $query = $db->query("CALL getall_conducted_appointments_for_doctor(?, ?)", [$id, NULL]);
            } else {
                $query = $db->query("CALL getall_conducted_appointments_for_doctor(NULL, ?)", [NULL]);
            }
            $result = $query->getRowArray();
            // print_r($result);die;
            if ($result && !empty($result['result_json'])) {
                $data = json_decode($result['result_json'], true);
                return $this->response->setJSON([
                    'status' => 200,
                    'data' => $data['data'],
                ])->setStatusCode(200);
            }
            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No records found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }


    public function getUpcomingAppointments()
    {
        try {
            $db = \Config\Database::connect();
            $query = $db->query("CALL get_upcoming_appointments(?)", [NULL]);
            $result = $query->getRowArray();
            if ($result && !empty($result['result_json'])) {
                $data = json_decode($result['result_json'], true);
                return $this->response->setJSON([
                    'status' => 200,
                    'data' => $data['data'], // Assuming the procedure returns the 'data' part correctly
                ])->setStatusCode(200);
            }
            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No cancelled appointments found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            // If there's an error, return a 500 response with the error message
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }
    public function get_upcoming_consultantwise_appointments()
    {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        $id = $input['userId'];
        try {
            $db = \Config\Database::connect();
            $query = $db->query("CALL get_upcoming_appointments_for_doctors(?, ?)", [$id, NULL]);
            $result = $query->getRowArray();
            if ($result && !empty($result['result_json'])) {
                $data = json_decode($result['result_json'], true);
                return $this->response->setJSON([
                    'status' => 200,
                    'data' => $data['data'], // Assuming the procedure returns the 'data' part correctly
                ])->setStatusCode(200);
            }
            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No cancelled appointments found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            // If there's an error, return a 500 response with the error message
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }
    public function getCancelledAppointments()
    {
        try {
            $db = \Config\Database::connect();
            $query = $db->query("CALL get_cancelled_appointments(?)", [NULL]);

            $result = $query->getRowArray();

            if ($result && !empty($result['result_json'])) {
                $data = json_decode($result['result_json'], true);

                return $this->response->setJSON([
                    'status' => 200,
                    'data' => $data['data'], // Assuming the procedure returns the 'data' part correctly
                ])->setStatusCode(200);
            }

            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No cancelled appointments found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            // If there's an error, return a 500 response with the error message
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }
    public function get_cancelledcounsultant_appointments()
    {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        $id = $input['userId'];
        try {
            $db = \Config\Database::connect();
            $query = $db->query("CALL get_cancelled_appointmentsbycounsultant(?)", [$id]);
            $result = $query->getRowArray();
            if ($result && !empty($result['result_json'])) {
                $data = json_decode($result['result_json'], true);
                return $this->response->setJSON([
                    'status' => 200,
                    'data' => $data['data'],
                ])->setStatusCode(200);
            }
            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No cancelled appointments found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }
    public function getPendingAppointments()
    {
        try {
            $db = \Config\Database::connect();
            $query = $db->query("CALL get_pending_appointments(?)", [NULL]);
            $result = $query->getRowArray();
            if ($result && !empty($result['result_json'])) {
                $data = json_decode($result['result_json'], true);
                return $this->response->setJSON([
                    'status' => 200,
                    'data' => $data['data'],
                ])->setStatusCode(200);
            }
            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No cancelled appointments found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }
    public function get_consultantwisepending_appointments()
    {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        $id = $input['userId'];
        try {
            $db = \Config\Database::connect();
            $query = $db->query("CALL get_pending_appointments_byDoctorId(?,?)", [$id, NULL]);
            $result = $query->getRowArray();
            if ($result && !empty($result['result_json'])) {
                $data = json_decode($result['result_json'], true);
                return $this->response->setJSON([
                    'status' => 200,
                    'data' => $data['data'],
                ])->setStatusCode(200);
            }
            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No cancelled appointments found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }
    public function fetchslotsforcustome()
    {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        //  echo "<pre>";print_r($input);exit();

        if (empty($input['branch_id'])) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'Required fields are missing.'
            ])->setStatusCode(400);
        }
        $branch_id = $input['branch_id'];
        $selected_date = isset($input['selected_date']) ? $input['selected_date'] : null; // Get the selected date from input

        $slots_builder = $this->db->table('tbl_slots');
        $slots_builder->where([
            'branch_id' => $branch_id,
            'is_deleted' => 'N',
            'section' => '6',
        ]);
        $slots = $slots_builder->get()->getResult();

        $booked_slots_builder = $this->db->table('tbl_booked_slots');
        $booked_slots_builder->select('slots_id, COUNT(slots_id) as booked_count, selected_date');
        $booked_slots_builder->where([
            'branch_id' => $branch_id,
            'is_deleted' => 'N',
        ]);

        if ($selected_date) {
            $booked_slots_builder->where('selected_date >=', $selected_date);
        }

        $booked_slots_builder->groupBy('slots_id, selected_date');
        $booked_slots_data = $booked_slots_builder->get()->getResultArray();

        $booked_slots_count = [];
        foreach ($booked_slots_data as $booked_slot) {
            $booked_slots_count[$booked_slot['slots_id']] = [
                'booked_count' => (int)$booked_slot['booked_count'],
                'selected_date' => $booked_slot['selected_date'],
            ];
        }
        $filtered_slots = array_filter($slots, function ($slot) use ($booked_slots_count, $selected_date) {
            $slot_id = $slot->id;
            $slot_count = isset($slot->slotcount) ? (int)$slot->slotcount : 0;
            $booked_count = isset($booked_slots_count[$slot_id]) ? (int)$booked_slots_count[$slot_id]['booked_count'] : 0;
            $booked_selected_date = isset($booked_slots_count[$slot_id]) ? $booked_slots_count[$slot_id]['selected_date'] : null;

            if ($selected_date && $booked_selected_date > $selected_date && $booked_count >= $slot_count) {
                return false; // Exclude if it's fully booked for a date after the selected date
            }

            return $booked_count < $slot_count;
        });

        return $this->response->setJSON([
            'status' => 200,
            'data' => array_values($filtered_slots)
        ])->setStatusCode(200);
    }
    public function get_where_condition_data($table, $role_ref_code)
    {
        // echo "hiii";
        // echo $role_ref_code; // This should now output the role_ref_code value
        // exit();

        try {
            $builder = $this->db->table($table);

            // Apply the custom where condition if provided
            if (!empty($role_ref_code)) {
                $builder->where('role_ref_code', $role_ref_code);
            }

            // Add the is_deleted condition
            $builder->where('is_deleted', 'N');

            $result = $builder->get()->getResultArray();

            if ($result) {
                return $this->response->setJSON([
                    'status' => 200,
                    'message' => 'Records retrieved successfully',
                    'data' => $result
                ])->setStatusCode(200);
            }
            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No records found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }
    // public function submitappointment()
    // {
    //     date_default_timezone_set('Asia/Kolkata');
    //     $rawInput = file_get_contents('php://input');
    //     $input = json_decode($rawInput, true);

    //     if (empty($input)) {
    //         return $this->response->setJSON(['status' => 400, 'message' => 'No data provided.'])->setStatusCode(400);
    //     }

    //     $date = isset($input['date']) ? (new DateTime($input['date']))->format('Y-m-d') : null;
    //     $startDate = isset($input['startDate']) ? (new DateTime($input['startDate']))->format('Y-m-d') : null;

    //     if ($date && !strtotime($date)) {
    //         return $this->response->setJSON(['status' => 400, 'message' => 'Invalid date format for "date".'])->setStatusCode(400);
    //     }
    //     if ($startDate && !strtotime($startDate)) {
    //         return $this->response->setJSON(['status' => 400, 'message' => 'Invalid date format for "startDate".'])->setStatusCode(400);
    //     }

    //     $slotId = $input['slot'] ?? null;
    //     if (!$slotId) {
    //         return $this->response->setJSON(['status' => 400, 'message' => 'Slot ID is required.'])->setStatusCode(400);
    //     }

    //     // Fetch slot price
    //     $slotQuery = $this->db->table('tbl_slots')
    //                           ->select('price')
    //                           ->where('id', $slotId)
    //                           ->get();
    //     $slotResult = $slotQuery->getRowArray();
    //     if (!$slotResult) {
    //         return $this->response->setJSON(['status' => 404, 'message' => 'Slot not found.'])->setStatusCode(404);
    //     }

    //     // Fetch service details (duration, price, etc.) from `tbl_servicemst`
    //     $hawservices = $input['hawservices'] ?? [];
    //     $serviceDetails = [];

    //     if (!empty($hawservices)) {
    //         $serviceQuery = $this->db->table('tbl_servicemst')
    //                                 ->select('id, name, duration, price')
    //                                 ->whereIn('id', $hawservices) // Fetch multiple services
    //                                 ->get();
    //         $serviceDetails = $serviceQuery->getResultArray();
    //     }

    //     // Add service details to input
    //     $input['service_details'] = $serviceDetails;

    //     // Convert updated input to JSON
    //     $inputData = json_encode($input);

    //     // print_r($inputData);exit();

    //     // Call the stored procedure
    //     $query = "CALL single_appointment(?::jsonb, ?)";
    //     $bindParams = [$inputData, null];
    //     $queryResult = $this->db->query($query, $bindParams);
    //     $result = $queryResult->getRowArray();

    //     $model = new HomeModel();
    //     $slotid = $input['slot'];
    //     $slottimeObj = $model->getslotstime($slotid);
    //     $slottime = $slottimeObj->slots_time; 

    //     if ($result && isset($result['result'])) {
    //         $resultData = json_decode($result['result'], true);

    //         return $this->response->setJSON($resultData)->setStatusCode($resultData['status']);
    //     } else {
    //         return $this->response->setJSON([
    //             'status' => 500,
    //             'message' => 'Error: Unexpected result from stored procedure.'
    //         ])->setStatusCode(500);
    //     }
    // }

    // public function submitappointment()
    // {
    //     date_default_timezone_set('Asia/Kolkata');
    //     $rawInput = file_get_contents('php://input');
    //     $input = json_decode($rawInput, true);
    //     print_r($input);die;


    //     if (empty($input)) {
    //         return $this->response->setJSON(['status' => 400, 'message' => 'No data provided.'])->setStatusCode(400);
    //     }

    //     $date = isset($input['date']) ? (new DateTime($input['date']))->format('Y-m-d') : null;
    //     $startDate = isset($input['startDate']) ? (new DateTime($input['startDate']))->format('Y-m-d') : null;

    //     if ($date && !strtotime($date)) {
    //         return $this->response->setJSON(['status' => 400, 'message' => 'Invalid date format for "date".'])->setStatusCode(400);
    //     }
    //     if ($startDate && !strtotime($startDate)) {
    //         return $this->response->setJSON(['status' => 400, 'message' => 'Invalid date format for "startDate".'])->setStatusCode(400);
    //     }

    //     $slotId = $input['slot'] ?? null;
    //     if (!$slotId) {
    //         return $this->response->setJSON(['status' => 400, 'message' => 'Slot ID is required.'])->setStatusCode(400);
    //     }

    //     $branchId = $input['branch'] ?? null;
    //     if (!$branchId) {
    //         return $this->response->setJSON(['status' => 400, 'message' => 'Branch ID is required.'])->setStatusCode(400);
    //     }

    //     // ✅ Step 1: Get all chairs for the branch
    //     $chairs = $this->db->table('tbl_chairs')
    //         ->select('id')
    //         ->where('branch_id', $branchId)
    //         ->orderBy('id', 'ASC')
    //         ->get()
    //         ->getResultArray();

    //     if (empty($chairs)) {
    //         return $this->response->setJSON(['status' => 404, 'message' => 'No chairs found for this branch.'])->setStatusCode(404);
    //     }

    //     // ✅ Step 2: Get already booked chairs for this date and slot
    //     $bookedChairs = $this->db->table('tbl_booked_slots')
    //         ->select('chair_ref_id')
    //         ->where('branch_id', $branchId)
    //         ->where('selected_date', $date)
    //         ->where('slots_id', $slotId)
    //         ->get()
    //         ->getResultArray();

    //     // print_r($bookedChairs);
    //     // exit();

    //     $bookedChairIds = array_column($bookedChairs, 'chair_ref_id');
    //     // print_r($bookedChairIds);exit();

    //     // ✅ Step 3: Assign first available chair
    //     $assignedChairId = null;
    //     foreach ($chairs as $chair) {
    //         if (!in_array($chair['id'], $bookedChairIds)) {
    //             $assignedChairId = $chair['id'];
    //             break;
    //         }
    //     }

    //     if (!$assignedChairId) {
    //         return $this->response->setJSON(['status' => 409, 'message' => 'All chairs are booked for the selected slot.'])->setStatusCode(409);
    //     }

    //     // ✅ Add chair_id to input
    //     $input['chair_ref_id'] = $assignedChairId;

    //     // ✅ Get service details
    //     $hawservices = $input['hawservices'] ?? [];
    //     $serviceDetails = [];

    //     if (!empty($hawservices)) {
    //         $serviceQuery = $this->db->table('tbl_servicemst')
    //             ->select('id, name, duration, price')
    //             ->whereIn('id', $hawservices)
    //             ->get();
    //         $serviceDetails = $serviceQuery->getResultArray();
    //     }

    //     $input['service_details'] = $serviceDetails;

    //     // ✅ Prepare data and call stored procedure
    //     $inputData = json_encode($input);

    //     $query = "CALL single_appointment(?::jsonb, ?)";
    //     $bindParams = [$inputData, null];
    //     $queryResult = $this->db->query($query, $bindParams);
    //     $result = $queryResult->getRowArray();

    //     $model = new HomeModel();
    //     $slotid = $input['slot'];
    //     $slottimeObj = $model->getslotstime($slotid);
    //     $slottime = $slottimeObj->slots_time;

    //     if ($result && isset($result['result'])) {
    //         $resultData = json_decode($result['result'], true);
    //         return $this->response->setJSON($resultData)->setStatusCode($resultData['status']);
    //     } else {
    //         return $this->response->setJSON([
    //             'status' => 500,
    //             'message' => 'Error: Unexpected result from stored procedure.'
    //         ])->setStatusCode(500);
    //     }
    // }

    public function submitappointment()
{
    date_default_timezone_set('Asia/Kolkata');
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (empty($input)) {
        return $this->response->setJSON(['status' => 400, 'message' => 'No data provided.'])->setStatusCode(400);
    }

    $date = isset($input['date']) ? (new DateTime($input['date']))->format('Y-m-d') : null;
    $startDate = isset($input['startDate']) ? (new DateTime($input['startDate']))->format('Y-m-d') : null;

    if ($date && !strtotime($date)) {
        return $this->response->setJSON(['status' => 400, 'message' => 'Invalid date format for "date".'])->setStatusCode(400);
    }

    if ($startDate && !strtotime($startDate)) {
        return $this->response->setJSON(['status' => 400, 'message' => 'Invalid date format for "startDate".'])->setStatusCode(400);
    }

    $slotId = $input['slot'] ?? null;
    $branchId = $input['branch'] ?? null;
    $chairRefId = isset($input['force_chair_ref_id']) ? (string)$input['force_chair_ref_id'] : null;

    if (!$slotId || !$branchId) {
        return $this->response->setJSON([
            'status' => 400,
            'message' => 'Slot ID and Branch ID are required.'
        ])->setStatusCode(400);
    }
    
    if (is_null($chairRefId)) {
        // Find all active chairs in the branch
        $activeChairs = $this->db->table('tbl_chairs')
            ->select('id')
            ->where([
                'branch_id' => $branchId,
                'is_active' => 'Y',
                'is_deleted' => 'N'
            ])
            ->get()->getResultArray();
    
        $availableChairId = null;
    
        foreach ($activeChairs as $chair) {
            $isBooked = $this->db->table('tbl_booked_slots')
                ->where([
                    'branch_id' => $branchId,
                    'selected_date' => $date,
                    'slots_id' => $slotId,
                    'chair_ref_id' => $chair['id'],
                    'is_deleted' => 'N'
                ])
                ->countAllResults();
    
            if ($isBooked == 0) {
                $availableChairId = $chair['id'];
                break;
            }
        }
    
        if ($availableChairId === null) {
            return $this->response->setJSON([
                'status' => 409,
                'message' => 'No available chair for the selected slot and date.'
            ])->setStatusCode(409);
        }
    
        $chairRefId = (string)$availableChairId;
    }
    
    $input['chair_ref_id'] = $chairRefId;
    

// print_r($inputData);exit();


    // if (!$slotId || !$branchId || !$chairRefId) {
    //     return $this->response->setJSON([
    //         'status' => 400,
    //         'message' => 'Slot ID, Branch ID, and Chair ID are required.'
    //     ])->setStatusCode(400);
    // }

    $inputData = json_encode($input);
    // ✅ (Optional) Validate that the selected chair is still free — safeguard if needed
    $isAlreadyBooked = $this->db->table('tbl_booked_slots')
        ->where([
            'branch_id' => $branchId,
            'selected_date' => $date,
            'slots_id' => $slotId,
            'chair_ref_id' => $chairRefId,
            'is_deleted' => 'N'
        ])
        ->countAllResults();

    if ($isAlreadyBooked > 0) {
        return $this->response->setJSON([
            'status' => 409,
            'message' => 'Selected chair is already booked for this slot.'
        ])->setStatusCode(409);
    }

    // ✅ Get service details
    $hawservices = $input['hawservices'] ?? [];
    $serviceDetails = [];

    if (!empty($hawservices)) {
        $serviceQuery = $this->db->table('tbl_servicemst')
            ->select('id, name, duration, price')
            ->whereIn('id', $hawservices)
            ->get();
        $serviceDetails = $serviceQuery->getResultArray();
    }

    $input['service_details'] = $serviceDetails;

    // ✅ Prepare data and call stored procedure
    $inputData = json_encode($input);
    // print_r($inputData);exit();

    $query = "CALL single_appointment(?::jsonb, ?)";
    $bindParams = [$inputData, null];
    $queryResult = $this->db->query($query, $bindParams);
    $result = $queryResult->getRowArray();

    // ✅ Fetch slot time for reference
    $model = new HomeModel();
    $slottimeObj = $model->getslotstime($slotId);
    $slottime = $slottimeObj->slots_time ?? null;

    if ($result && isset($result['result'])) {
        $resultData = json_decode($result['result'], true);
        return $this->response->setJSON($resultData)->setStatusCode($resultData['status']);
    } else {
        return $this->response->setJSON([
            'status' => 500,
            'message' => 'Error: Unexpected result from stored procedure.'
        ])->setStatusCode(500);
    }
}





    // public function submitappointments() // main function
    // {
    //     date_default_timezone_set('Asia/Kolkata');
    //     $rawInput = file_get_contents('php://input');
    //     $input = json_decode($rawInput, true);
    //     // print_r($input);die;
    //     $model = new HomeModel();
    //     $branch_id = $input['branch'];
    //     $section_id = $input['section'];
    //     $getconsultantid = $model->getidbybranch($branch_id, $section_id);
    //     $consultant_id = !empty($getconsultantid) && isset($getconsultantid[0]) ? $getconsultantid[0] : null;

    //     if (!empty($input['subscriptionType'])) {
    //         preg_match('/\d+/', $input['subscriptionType'], $matches);
    //         $numOccurrences = isset($matches[0]) ? (int)$matches[0] : 0;

    //         if ($numOccurrences > 0 && isset($input['startDate']) && isset($input['slots'])) {
    //             $startDate = new DateTime($input['startDate']);
    //             $daysToBook = array_keys($input['slots']);
    //             $bookedDates = [];

    //             $this->db->transStart();

    //             try {
    //                 $bookedCount = 0;
    //                 $startDayName = $startDate->format('l'); 
    //                 $foundStartDay = false;
    //                 while ($bookedCount < $numOccurrences) {
    //                     foreach ($daysToBook as $dayName) {
    //                         if ($bookedCount >= $numOccurrences) break;
    //                         if (!$foundStartDay) {
    //                             if ($dayName === $startDayName) {
    //                                 $foundStartDay = true;
    //                             } else {
    //                                 continue;
    //                             }
    //                         }
    //                         $appointmentDate = clone $startDate;
    //                         while ($appointmentDate->format('l') !== $dayName) {
    //                             $appointmentDate->modify('next ' . $dayName); 
    //                         }
    //                         if (!in_array($appointmentDate->format('Y-m-d'), $bookedDates)) {
    //                             if (isset($input['slots'][$dayName]['id'])) {
    //                                 $slotId = $input['slots'][$dayName]['id'];
    //                                 $branch = !empty($input['branch']) && is_numeric($input['branch']) ? (int)$input['branch'] : null;
    //                                 $slotId = !empty($slotId) && is_numeric($slotId) ? (int)$slotId : null;

    //                                 if ($branch === null || $slotId === null) {
    //                                     throw new \Exception('Invalid input for branch or slot');
    //                                 }

    //                                 $appointmentData = [
    //                                     'branch' => $branch,
    //                                     'hawservices' => !empty($input['hawservices']) ? $input['hawservices'] : null,
    //                                     'consultant' => !empty($input['consultant']) ? $input['consultant'] : $consultant_id,
    //                                     'healthissues' => !empty($input['healthissues']) ? $input['healthissues'] : null,
    //                                     'dates' => $appointmentDate->format('Y-m-d'),
    //                                     'full_name' => !empty($input['full_name']) ? $input['full_name'] : null,
    //                                     'age' => !empty($input['age']) ? $input['age'] : null,
    //                                     'mobile_no' => !empty($input['mobile_no']) ? $input['mobile_no'] : null,
    //                                     'email_id' => !empty($input['email_id']) ? $input['email_id'] : null,
    //                                     'locations' => !empty($input['location']) ? $input['location'] : null,
    //                                     'slot' => $slotId,
    //                                     'subscription' => !empty($input['subscription']) ? $input['subscription'] : null,
    //                                     'plan' => !empty($input['plan']) ? $input['plan'] : null,
    //                                     'subscriptiontype' => !empty($input['subscriptionType']) ? $input['subscriptionType'] : null,
    //                                     'startdate' => !empty($input['startDate']) ? $input['startDate'] : null,
    //                                     'section' => !empty($input['section']) ? $input['section'] : null,
    //                                     'appointment_status' => 'PE'
    //                                 ];

    //                                 $this->db->table('tbl_appointment')->insert($appointmentData);
    //                                 $appointmentId = $this->db->insertID();

    //                                 $exerciseBookedSlotData = [
    //                                     'slots_id' => $slotId,
    //                                     'status' => 'B',
    //                                     'branch_id' => $branch,
    //                                     'selected_date' => $appointmentDate->format('Y-m-d'),
    //                                     'ap_id' => $appointmentId,
    //                                 ];

    //                                 $this->db->table('tbl_booked_slots')->insert($exerciseBookedSlotData);

    //                                 $bookedDates[] = $appointmentDate->format('Y-m-d');
    //                                 $bookedCount++;
    //                             }
    //                         }

    //                         $startDate->modify('+1 day');
    //                     }
    //                 }

    //                 $this->db->transComplete();

    //                 if ($this->db->transStatus() === false) {
    //                     throw new \Exception('Transaction failed');
    //                 }
    //                 return $this->response->setJSON(['status' => 200, 'message' => 'Appointments created successfully.'])->setStatusCode(200);

    //             } catch (\Exception $e) {
    //                 // Rollback transaction if something fails
    //                 $this->db->transRollback();
    //                 log_message('error', 'Failed to create appointments: ' . $e->getMessage());

    //                 return $this->response->setJSON([
    //                     'status' => 500,
    //                     'message' => 'Failed to create appointments.',
    //                     'error' => $e->getMessage()
    //                 ])->setStatusCode(500);
    //             }
    //         }
    //     }

    //     return $this->response->setJSON(['status' => 400, 'message' => 'Invalid input data.'])->setStatusCode(400);
    // }

    public function submitappointments()
    {
        date_default_timezone_set('Asia/Kolkata');
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        print_r($input);
        die;
        $model = new HomeModel();
        $branch_id = $input['branch'];
        $section_id = $input['section'];
        $getconsultantid = $model->getidbybranch($branch_id, $section_id);
        $consultant_id = !empty($getconsultantid) && isset($getconsultantid[0]) ? $getconsultantid[0] : null;

        if (!empty($input['subscriptionType'])) {
            preg_match('/\d+/', $input['subscriptionType'], $matches);
            $numOccurrences = isset($matches[0]) ? (int)$matches[0] : 0;

            if ($numOccurrences > 0 && isset($input['startDate']) && isset($input['slots'])) {
                $startDate = new DateTime($input['startDate']);
                $daysToBook = array_keys($input['slots']);
                $bookedDates = [];

                $this->db->transStart();

                try {
                    $bookedCount = 0;
                    $startDayName = $startDate->format('l');
                    $foundStartDay = false;
                    while ($bookedCount < $numOccurrences) {
                        foreach ($daysToBook as $dayName) {
                            if ($bookedCount >= $numOccurrences) break;
                            if (!$foundStartDay) {
                                if ($dayName === $startDayName) {
                                    $foundStartDay = true;
                                } else {
                                    continue;
                                }
                            }
                            $appointmentDate = clone $startDate;
                            while ($appointmentDate->format('l') !== $dayName) {
                                $appointmentDate->modify('next ' . $dayName);
                            }
                            if (!in_array($appointmentDate->format('Y-m-d'), $bookedDates)) {
                                if (isset($input['slots'][$dayName]['id'])) {
                                    $slotId = $input['slots'][$dayName]['id'];
                                    $branch = !empty($input['branch']) && is_numeric($input['branch']) ? (int)$input['branch'] : null;
                                    $slotId = !empty($slotId) && is_numeric($slotId) ? (int)$slotId : null;

                                    if ($branch === null || $slotId === null) {
                                        throw new \Exception('Invalid input for branch or slot');
                                    }

                                    $appointmentData = [
                                        'branch' => $branch,
                                        'hawservices' => !empty($input['hawservices']) ? $input['hawservices'] : null,
                                        'consultant' => !empty($input['consultant']) ? $input['consultant'] : $consultant_id,
                                        'healthissues' => !empty($input['healthissues']) ? $input['healthissues'] : null,
                                        'dates' => $appointmentDate->format('Y-m-d'),
                                        'full_name' => !empty($input['full_name']) ? $input['full_name'] : null,
                                        'age' => !empty($input['age']) ? $input['age'] : null,
                                        'mobile_no' => !empty($input['mobile_no']) ? $input['mobile_no'] : null,
                                        'email_id' => !empty($input['email_id']) ? $input['email_id'] : null,
                                        'locations' => !empty($input['location']) ? $input['location'] : null,
                                        'slot' => $slotId,
                                        'subscription' => !empty($input['subscription']) ? $input['subscription'] : null,
                                        'plan' => !empty($input['plan']) ? $input['plan'] : null,
                                        'subscriptiontype' => !empty($input['subscriptionType']) ? $input['subscriptionType'] : null,
                                        'startdate' => !empty($input['startDate']) ? $input['startDate'] : null,
                                        'section' => !empty($input['section']) ? $input['section'] : null,
                                        'appointment_status' => 'PE'
                                    ];

                                    $this->db->table('tbl_appointment')->insert($appointmentData);
                                    $appointmentId = $this->db->insertID();

                                    $exerciseBookedSlotData = [
                                        'slots_id' => $slotId,
                                        'status' => 'B',
                                        'branch_id' => $branch,
                                        'selected_date' => $appointmentDate->format('Y-m-d'),
                                        'ap_id' => $appointmentId,
                                    ];

                                    $this->db->table('tbl_booked_slots')->insert($exerciseBookedSlotData);

                                    $bookedDates[] = $appointmentDate->format('Y-m-d');
                                    $bookedCount++;
                                }
                            }

                            $startDate->modify('+1 day');
                        }
                    }

                    $this->db->transComplete();

                    if ($this->db->transStatus() === false) {
                        throw new \Exception('Transaction failed');
                    }
                    $slottime = $input['slots'][$dayName]['time']; // Extract time dynamically
                    $selectedDay = $dayName; // Extract day dynamically
                    // print_r($slottime);die;
                    $receiverMsg = view('subscribtionemailadmin', [
                        'full_name' => $input['full_name'],
                        'mobile_no' => $input['mobile_no'],
                        'email_id' => $input['email_id'],
                        'location' => $input['location'],
                        'date' => $input['startDate'],
                        'slots'  => $input['slots'],
                        'slottime' => $slottime,
                        'day' => $selectedDay,
                    ]);
                    $senderMsg = view('subscribtionemailuser', [
                        'full_name' => $input['full_name'], // Match variable in the view
                        'mobile_no' => $input['mobile_no'],
                        'email_id' => $input['email_id'],
                        'location' => $input['location'],
                        'date' => $input['startDate'],
                        'slots'  => $input['slots'],
                        'slottime' => $slottime,
                        'day' => $selectedDay,
                    ]);


                    $useremail = $input['email_id'];
                    $consultantEmailObj = $model->getemail($consultant_id); // Fetch consultant email
                    $cemail = $consultantEmailObj ? $consultantEmailObj->email : null; // Extract email property
                    $ccEmails = ['siddheshkadge214@gmail.com', $cemail];
                    $appointmentDateTime = $input['date'];
                    $receiverSubject = 'Your Appointment is booked Successfully.';
                    $senderSubject = 'You Have a New Appointment of ' . $input['full_name'] . '';
                    sendConfirmationEmail($useremail, $ccEmails, $receiverSubject, $receiverMsg, $senderSubject, $senderMsg);

                    return $this->response->setJSON(['status' => 200, 'message' => 'Appointments created successfully.'])->setStatusCode(200);
                } catch (\Exception $e) {
                    // Rollback transaction if something fails
                    $this->db->transRollback();
                    log_message('error', 'Failed to create appointments: ' . $e->getMessage());

                    return $this->response->setJSON([
                        'status' => 500,
                        'message' => 'Failed to create appointments.',
                        'error' => $e->getMessage()
                    ])->setStatusCode(500);
                }
            }
        }

        return $this->response->setJSON(['status' => 400, 'message' => 'Invalid input data.'])->setStatusCode(400);
    }


    public function submitPayment()
    {
        $data = $this->request->getJSON(true); // Passing `true` converts JSON to an associative array

        // Validate required fields
        if (!isset($data['id']) || !isset($data['price']) || !isset($data['payment_status']) || !isset($data['appointment_status'])) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'Invalid data provided'
            ])->setStatusCode(400);
        }

        // Load model if not loaded
        $model = new HomeModel();

        // Prepare data to update payment
        $paymentData = [
            'id' => $data['id'],
            'price' => $data['price'],
            'payment_status' => $data['payment_status'],
            'appointment_status' => $data['appointment_status'],
        ];

        // Update the payment record in the database
        $updated = $model->updatePayment($paymentData);

        if (!$updated) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'Failed to update payment'
            ])->setStatusCode(500);
        }

        // Successful response
        return $this->response->setJSON([
            'status' => 200,
            'message' => 'Payment submitted successfully'
        ])->setStatusCode(200);
    }

    public function readAppointments($id = null)
    {
        $data = $this->request->getJSON(true);
        $id = isset($data['id']) ? $data['id'] : null;
        $role_ref_code = isset($data['role_ref_code']) ? $data['role_ref_code'] : null;
        // print_r($role_ref_code);die;
        try {
            $db = \Config\Database::connect();
            if ($role_ref_code == 'Consultant') {
                $query = $db->query("CALL spReadAppointments(?, ?)", [$id, NULL]);
            } else {
                $query = $db->query("CALL spReadAppointments(NULL, ?)", [NULL]);
            }
            $result = $query->getRowArray();
            if ($result && !empty($result['result_json'])) {
                return $this->response->setJSON([
                    'status' => 200,
                    'message' => 'Records retrieved successfully',
                    'data' => json_decode($result['result_json'], true)
                ])->setStatusCode(200);
            }
            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No records found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }

    // public function getUserDetails($userId)
    // {
    //     // Get token from the request headers
    //     $authHeader = $this->request->getHeader('Authorization');
    //     $token = $authHeader ? $authHeader->getValue() : '';

    //     if (!$token) {
    //         return $this->response->setJSON([
    //             'status' => 401,
    //             'message' => 'Token missing'
    //         ])->setStatusCode(401);
    //     }

    //     try {
    //         $user = $this->db->table('tbl_register')
    //                  ->where('id', $userId)
    //                  ->get()
    //                  ->getRowArray();

    //         if ($user) {
    //             return $this->response->setJSON([
    //                 'status' => 200,
    //                 'user' => $user
    //             ])->setStatusCode(200);
    //         } else {
    //             return $this->response->setJSON([
    //                 'status' => 404,
    //                 'message' => 'User not found'
    //             ])->setStatusCode(404);
    //         }

    //     } catch (Exception $e) {
    //         // Log the error to help with debugging
    //         log_message('error', 'Error: ' . $e->getMessage());
    //         return $this->response->setJSON([
    //             'status' => 500,
    //             'message' => 'Internal Server Error'
    //         ])->setStatusCode(500);
    //     }
    // }

    public function getUserDetails($userId)
    {
        // Get token from the request headers
        $authHeader = $this->request->getHeader('Authorization');
        $token = $authHeader ? $authHeader->getValue() : '';

        if (!$token) {
            return $this->response->setJSON([
                'status' => 401,
                'message' => 'Token missing'
            ])->setStatusCode(401);
        }

        try {
            // Fetch user details from tbl_register
            $user = $this->db->table('tbl_register')
                ->where('id', $userId)
                ->get()
                ->getRowArray();

            if (!$user) {
                return $this->response->setJSON([
                    'status' => 404,
                    'message' => 'User not found'
                ])->setStatusCode(404);
            }

            // If user is a stylist (STL), fetch data from tbl_stylists
            if ($user['role_ref_code'] === 'STL') {
                $stylistData = $this->db->table('tbl_stylists')
                    ->where('id', $user['user_code_ref']) // Ensure user_code_ref exists
                    ->where('is_active', 'Y')
                    ->where('is_deleted', 'N')
                    ->get()
                    ->getRowArray();

                if ($stylistData) {
                    $user['access_levels'] = json_decode($stylistData['access_levels'], true); // Convert to array
                }
            }

            return $this->response->setJSON([
                'status' => 200,
                'user' => $user
            ])->setStatusCode(200);
        } catch (Exception $e) {
            // Log the error to help with debugging
            log_message('error', 'Error: ' . $e->getMessage());
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'Internal Server Error'
            ])->setStatusCode(500);
        }
    }



    public function canceledshedule($table, $id)
    {
        $updateData = ['appointment_status' => 'CN'];
        if ($this->db->table($table)->where('id', $id)->update($updateData)) {
            return $this->response->setJSON([
                'status' => 200,
                'message' => 'Record marked as deleted successfully'
            ])->setStatusCode(200);
        }
        return $this->response->setJSON([
            'status' => 500,
            'message' => 'Failed to mark the record as deleted'
        ])->setStatusCode(500);
    }
    public function getScheduleData()
    {
        // Get the branchId and consultantId from query parameters
        $branchId = $this->request->getVar('branchId');
        $consultantId = $this->request->getVar('consultantId');

        // Check if both branchId and consultantId are provided
        if (!$branchId || !$consultantId) {
            return $this->response->setStatusCode(400)->setJSON(['message' => 'Both branchId and consultantId are required.']);
        }

        // Fetch the schedule data directly from the database based on branchId and consultantId
        $builder = $this->db->table('tbl_schedule');  // Replace 'tbl_schedule' with your actual schedule table name

        // Ensure you are using the correct column names, e.g., "branch_id" and "doctor_id"
        $builder->where('branch_id', $branchId);
        $builder->where('doctor_id', $consultantId); // Adjust column name here if necessary (doctor_id vs consultant_id)

        // Run the query
        $query = $builder->get();

        // Check if any data is returned
        if ($query->getNumRows() > 0) {
            // Return success response with data
            return $this->response->setStatusCode(200)->setJSON(['status' => 200, 'data' => $query->getResult()]);
        } else {
            // No data found for the given parameters
            return $this->response->setStatusCode(404)->setJSON(['status' => 404, 'message' => 'No schedule data found.']);
        }
    }



    // public function getslotsss($table)
    // {
    //     $consultantId = $this->request->getPost('consultantId') ?? $this->request->getJSON()->consultantId;
    //     $branchId = $this->request->getPost('branchId') ?? $this->request->getJSON()->branchId;

    //     try {
    //         // $builder = $this->db->table($table)->where('is_deleted', 'N');
    //         $builder = $this->db->table($table)->whereIn('is_deleted', ['N', 'Y']);
    //         if ($consultantId) {
    //             $builder->where('doctor_id', intval($consultantId));
    //         }

    //         if ($branchId) {
    //             $builder->where('branch_id', intval($branchId));
    //         }

    //         $result = $builder->get()->getResultArray();
    //         print_r($result);die;

    //         if ($result) {
    //             return $this->response->setJSON([
    //                 'status' => 200,
    //                 'message' => 'Records retrieved successfully',
    //                 'data' => $result
    //             ])->setStatusCode(200);
    //         }

    //         return $this->response->setJSON([
    //             'status' => 404,
    //             'message' => 'No records found',
    //             'data' => []
    //         ])->setStatusCode(404);

    //     } catch (\Exception $e) {
    //         return $this->response->setJSON([
    //             'status' => 500,
    //             'message' => 'An error occurred: ' . $e->getMessage(),
    //             'data' => []
    //         ])->setStatusCode(500);
    //     }
    // }
    public function getslotsss($table)
    {
        $consultantId = $this->request->getPost('consultantId') ?? $this->request->getJSON()->consultantId;
        $branchId = $this->request->getPost('branchId') ?? $this->request->getJSON()->branchId;

        // Get current day name
        $currentDayName = date('l'); // e.g., 'Tuesday'

        try {
            $builder = $this->db->table($table)->whereIn('is_deleted', ['N', 'Y']);

            // Add filters for doctor_id and branch_id
            if ($consultantId) {
                $builder->where('doctor_id', intval($consultantId));
            }
            if ($branchId) {
                $builder->where('branch_id', intval($branchId));
            }

            // Filter by current day name
            $builder->where('day_name', $currentDayName);

            $result = $builder->get()->getResultArray();
            // print_r($result); // Debug output
            // die;

            if ($result) {
                return $this->response->setJSON([
                    'status' => 200,
                    'message' => 'Records retrieved successfully',
                    'data' => $result
                ])->setStatusCode(200);
            }

            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No records found',
                'data' => []
            ])->setStatusCode(404);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ])->setStatusCode(500);
        }
    }

    public function patient_details()
    {
        // Get the request data (JSON payload)
        $request  = $this->request->getJSON(true);
        log_message('debug', 'Received data: ' . print_r($request, true));  // Log the incoming data


        // Validate integer fields
        $integerFields = ['systolic', 'diastolic', 'fasting', 'pp', 'weight', 'bmi'];

        foreach ($integerFields as $field) {
            if (isset($request[$field]) && $request[$field] === '') {
                $request[$field] = null;  // Convert empty string to NULL
            }
        }


        // Validate date field separately
        if (isset($request['followUpDate']) && $request['followUpDate'] === '') {
            $request['followUpDate'] = null;  // Convert empty string to NULL
        }

        if (empty($request)) {
            return $this->failValidationErrors('No data provided or invalid data format.');
        }

        $appointment_id = $request['appointment_id']; // Check if this is being set properly
        $fees = $request['fees']; // Similarly, check fees

        // Check if these values are correctly retrieved from $data
        log_message('debug', "Appointment ID: " . $appointment_id);
        log_message('debug', "Fees: " . $fees);

        $appointment_id = $request['appointment_id'];  // Get the appointment ID from the request data

        // Check if a record already exists for the given appointment_id
        $existingRecord = $this->db->table('tbl_patient_records')
            ->where('appointment_id', $appointment_id)
            ->get()
            ->getRowArray();

        if ($existingRecord) {
            // Record exists, update it
            try {
                $this->db->table('tbl_patient_records')
                    ->where('appointment_id', $appointment_id)
                    ->update($request);  // Update the existing record

                return $this->respondCreated([
                    'status'  => true,
                    'message' => 'Patient details saved successfully.',
                ]);
            } catch (\Exception $e) {
                return $this->failServerError('Failed to update patient details: ' . $e->getMessage());
            }
        } else {
            // Record doesn't exist, insert a new one
            try {
                $this->db->table('tbl_patient_records')->insert($request);  // Insert the new record

                return $this->respondCreated([
                    'status'  => true,
                    'message' => 'Patient details saved successfully.',
                ]);
            } catch (\Exception $e) {
                log_message('error', 'Error inserting data: ' . $e->getMessage());
                return $this->failServerError('Failed to save patient details: ' . $e->getMessage());
            }
        }
    }



    // public function submit_prescriptions()
    // {
    //     $data = $this->request->getJSON(true);  // Parse JSON payload

    //     // Check if data is valid
    //     if (empty($data) || empty($data['appointment_id']) || empty($data['prescriptions'])) {
    //         return $this->failValidationErrors('No data provided or invalid data format.');
    //     }

    //     $this->db->transStart();  // Start transaction

    //     try {
    //         foreach ($data['prescriptions'] as $prescription) {
    //             $insertData = [
    //                 'appointment_id'  => $data['appointment_id'],
    //                 'medicine'        => $prescription['medicine'],
    //                 'unit'            => $prescription['unit'],
    //                 'morning'         => !empty($prescription['morning']) ? 'Y' : 'N',
    //                 'afternoon'       => !empty($prescription['afternoon']) ? 'Y' : 'N',
    //                 'evening'         => !empty($prescription['evening']) ? 'Y' : 'N',
    //                 'days'            => $prescription['days'],
    //                 'record_date'     => $data['record_date'],
    //             ];

    //             $result = $this->db->table('tbl_prescriptions')->insert($insertData);

    //             // Check if insertion failed
    //             if (!$result) {
    //                 throw new \Exception('Failed to insert prescription record. DB Error: ' . $this->db->error()['message']);
    //             }
    //         }

    //         $this->db->transComplete();

    //         if ($this->db->transStatus() === false) {
    //             return $this->failServerError('Failed to save patient prescriptions.');
    //         }

    //         return $this->respondCreated([
    //             'status'  => true,
    //             'message' => 'Patient prescriptions saved successfully.',
    //         ]);

    //     } catch (\Exception $e) {
    //         $this->db->transRollback();  // Rollback transaction on error
    //         return $this->failServerError('Failed to save patient details: ' . $e->getMessage());
    //     }

    // }

    // public function submit_prescriptions()
    // {
    //     $data = $this->request->getJSON(true);  // Parse JSON payload
    //     // print_r($data);

    //     // Check if data is valid
    //     if (empty($data) || empty($data['appointment_id']) || empty($data['prescriptions'])) {
    //         return $this->failValidationErrors('No data provided or invalid data format.');
    //     }

    //     $this->db->transStart();  // Start transaction

    //     try {
    //         foreach ($data['prescriptions'] as $prescription) {
    //             $insertData = [
    //                 'appointment_id'  => $data['appointment_id'],
    //                 'medicine'        => $prescription['medicine'],
    //                 'unit'            => $prescription['unit'],
    //                 'morning'         => !empty($prescription['morning']) ? 'Y' : 'N',
    //                 'afternoon'       => !empty($prescription['afternoon']) ? 'Y' : 'N',
    //                 'evening'         => !empty($prescription['evening']) ? 'Y' : 'N',
    //                 'days'            => $prescription['days'],
    //                 'liquidperday'    => $prescription['liquidPerDay'],
    //                 'record_date'     => $prescription['record_date'],
    //             ];

    //             // Check if the prescription already exists for the given appointment_id
    //             $existingPrescription = $this->db->table('tbl_prescriptions')
    //                                             ->where('appointment_id', $data['appointment_id'])
    //                                             ->where('record_date', $prescription['record_date'])  // You can add more conditions if needed
    //                                             ->get()
    //                                             ->getRowArray();

    //             if ($existingPrescription) {
    //                 // If prescription exists, update the record
    //                 $result = $this->db->table('tbl_prescriptions')
    //                                    ->where('appointment_id', $data['appointment_id'])
    //                                    ->where('record_date', $prescription['record_date'])
    //                                    ->update($insertData);  // Update the prescription

    //                 if (!$result) {
    //                     throw new \Exception('Failed to update prescription record. DB Error: ' . $this->db->error()['message']);
    //                 }
    //             } else {
    //                 // If prescription doesn't exist, insert a new record
    //                 $result = $this->db->table('tbl_prescriptions')->insert($insertData);

    //                 // Check if insertion failed
    //                 if (!$result) {
    //                     throw new \Exception('Failed to insert prescription record. DB Error: ' . $this->db->error()['message']);
    //                 }
    //             }
    //         }

    //         $this->db->transComplete();

    //         if ($this->db->transStatus() === false) {
    //             return $this->failServerError('Failed to save patient prescriptions.');
    //         }

    //         return $this->respondCreated([
    //             'status'  => true,
    //             'message' => 'Patient prescriptions saved or updated successfully.',
    //         ]);

    //     } catch (\Exception $e) {
    //         $this->db->transRollback();  // Rollback transaction on error
    //         return $this->failServerError('Failed to save patient prescriptions: ' . $e->getMessage());
    //     }
    // }

    public function submit_prescriptions()
    {
        $data = $this->request->getJSON(true);
        log_message('debug', 'prescription: ' . print_r($data, true));

        if (empty($data) || empty($data['appointment_id']) || empty($data['prescriptions'])) {
            return $this->failValidationErrors('No data provided or invalid data format.');
        }

        $this->db->transStart();

        try {
            foreach ($data['prescriptions'] as $prescription) {
                $existingPrescription = $this->db->table('tbl_prescriptions')
                    ->where('appointment_id', $data['appointment_id'])
                    ->where('medicine', $prescription['medicine'])
                    ->where('record_date', $prescription['record_date'])
                    ->get()
                    ->getRowArray();

                $insertData = [
                    'appointment_id'  => $data['appointment_id'],
                    'medicine'        => $prescription['medicine'],
                    'unit'            => $prescription['unit'],
                    'morning'         => !empty($prescription['morning']) ? 'Y' : 'N',
                    'afternoon'       => !empty($prescription['afternoon']) ? 'Y' : 'N',
                    'evening'         => !empty($prescription['evening']) ? 'Y' : 'N',
                    'days'            => $prescription['days'],
                    'liquidperday'    => $prescription['liquidPerDay'],
                    'record_date'     => $prescription['record_date'],
                    'timing'          => $prescription['timing'] ?? null
                ];

                if ($existingPrescription) {
                    // Update if the prescription exists
                    $this->db->table('tbl_prescriptions')
                        ->where('id', $existingPrescription['id'])
                        ->update($insertData);
                } else {
                    // Insert if it doesn't exist
                    if (!$this->db->table('tbl_prescriptions')->insert($insertData)) {
                        throw new \Exception('Failed to insert prescription record.');
                    }
                }
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return $this->failServerError('Failed to save patient prescriptions.');
            }

            return $this->respondCreated([
                'status'  => true,
                'message' => 'Patient prescriptions saved successfully.',
            ]);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->failServerError('Failed to save patient prescriptions: ' . $e->getMessage());
        }
    }





    // public function submit_tests()
    //     {
    //         $data = $this->request->getJSON(true);  // Parse JSON payload

    //         if (empty($data) || !isset($data['tests'])) {
    //             return $this->failValidationErrors('Invalid data format or no tests provided.');
    //         }

    //         try {
    //             foreach ($data['tests'] as $test) {
    //                 $this->db->table('tbl_recommended_tests')->insert([
    //                     'appointment_id'  => $data['appointment_id'],
    //                     'test_type'   => $test['testType'],
    //                     'test_name'   => $test['testName'],
    //                     'record_date' => $data['record_date']
    //                 ]);
    //             }

    //             return $this->respondCreated([
    //                 'status'  => true,
    //                 'message' => 'Tests saved successfully.',
    //             ]);
    //         } catch (\Exception $e) {
    //             return $this->failServerError('Failed to save tests: ' . $e->getMessage());
    //         }
    //     }

    // public function submit_tests()
    // {
    //     $data = $this->request->getJSON(true);  // Parse JSON payload

    //     // Check if data and tests are provided
    //     if (empty($data) || !isset($data['tests'])) {
    //         return $this->failValidationErrors('Invalid data format or no tests provided.');
    //     }

    //     // Start a database transaction
    //     $this->db->transStart();

    //     try {
    //         foreach ($data['tests'] as $test) {
    //             // Check if the record with the same appointment_id and test already exists
    //             $existingTest = $this->db->table('tbl_recommended_tests')
    //                                      ->where('appointment_id', $data['appointment_id'])
    //                                      ->where('test_name', $test['testName'])  // Check by test_name or any other unique identifier
    //                                      ->get()
    //                                      ->getRowArray();

    //             if ($existingTest) {
    //                 // If record exists, update the existing test
    //                 $this->db->table('tbl_recommended_tests')
    //                          ->where('appointment_id', $data['appointment_id'])
    //                          ->where('test_name', $test['testName'])
    //                          ->update([
    //                              'test_type'   => $test['testType'],
    //                              'record_date' => $data['record_date'],
    //                          ]);
    //             } else {
    //                 // If no record exists, insert a new record
    //                 $this->db->table('tbl_recommended_tests')->insert([
    //                     'appointment_id'  => $data['appointment_id'],
    //                     'test_type'       => $test['testType'],
    //                     'test_name'       => $test['testName'],
    //                     'record_date'     => $data['record_date']
    //                 ]);
    //             }
    //         }

    //         // Complete the transaction
    //         $this->db->transComplete();

    //         // Check the transaction status
    //         if ($this->db->transStatus() === false) {
    //             return $this->failServerError('Failed to save tests.');
    //         }

    //         return $this->respondCreated([
    //             'status'  => true,
    //             'message' => 'Tests saved/updated successfully.',
    //         ]);

    //     } catch (\Exception $e) {
    //         $this->db->transRollback();  // Rollback the transaction if an error occurs
    //         return $this->failServerError('Failed to save tests: ' . $e->getMessage());
    //     }
    // }

    public function submit_tests()
    {
        $data = $this->request->getJSON(true);  // Parse JSON payload

        // Check if data and tests are provided
        if (empty($data) || !isset($data['tests'])) {
            return $this->failValidationErrors('Invalid data format or no tests provided.');
        }

        // Start a database transaction
        $this->db->transStart();

        try {
            foreach ($data['tests'] as $test) {
                // Ensure the test_id is provided and valid
                if (empty($test['test_id']) || empty($test['test_name']) || empty($test['test_type'])) {
                    return $this->failValidationErrors('Test data is incomplete.');
                }

                // Check if the record with the same appointment_id and test_id already exists
                $existingTest = $this->db->table('tbl_recommended_tests')
                    ->where('appointment_id', $data['appointment_id'])
                    ->where('test_id', $test['test_id'])  // Using test_id for uniqueness
                    ->get()
                    ->getRowArray();

                if ($existingTest) {
                    // If record exists, update the existing test
                    $this->db->table('tbl_recommended_tests')
                        ->where('appointment_id', $data['appointment_id'])
                        ->where('test_id', $test['test_id'])
                        ->update([
                            'test_type'   => $test['test_type'],
                            'record_date' => $data['record_date'],
                        ]);
                } else {
                    // If no record exists, insert a new record
                    $this->db->table('tbl_recommended_tests')->insert([
                        'appointment_id'  => $data['appointment_id'],
                        'test_id'         => $test['test_id'],  // Use test_id
                        'test_type'       => $test['test_type'],
                        'test_name'       => $test['test_name'],
                        'record_date'     => $data['record_date']
                    ]);
                }
            }

            // Complete the transaction
            $this->db->transComplete();

            // Check the transaction status
            if ($this->db->transStatus() === false) {
                return $this->failServerError('Failed to save tests.');
            }

            return $this->respondCreated([
                'status'  => true,
                'message' => 'Tests saved/updated successfully.',
            ]);
        } catch (\Exception $e) {
            $this->db->transRollback();  // Rollback the transaction if an error occurs
            return $this->failServerError('Failed to save tests: ' . $e->getMessage());
        }
    }


    public function submit_certifate_details()
    {
        // Get the request data (JSON payload)
        $request = $this->request->getJSON(true); // Get JSON data from request
        log_message('debug', 'Received data: ' . print_r($request, true)); // Log the incoming data

        // Check for missing keys before accessing them
        if (isset($request['certificates']) && !empty($request['certificates'])) {
            $certificates = $request['certificates'];
        } else {
            log_message('error', 'Certificates key is missing or empty');
            return $this->failValidationErrors('Certificates data is missing or invalid.');
        }

        // Check if the appointment_id is present
        if (!isset($request['appointment_id'])) {
            log_message('error', 'Appointment ID is missing');
            return $this->failValidationErrors('Appointment ID is missing.');
        }

        $appointment_id = $request['appointment_id']; // Get the appointment ID from the request data
        log_message('debug', "Appointment ID: " . $appointment_id);

        // Get record_date from the request
        $record_date = isset($request['record_date']) ? $request['record_date'] : date('Y-m-d'); // Use provided record_date or the current date

        // Check if a record already exists for the given appointment_id
        $existingRecord = $this->db->table('tbl_certificate')
            ->where('appointment_id', $appointment_id)
            ->get()
            ->getRowArray();

        if ($existingRecord) {
            // Record exists, update it
            try {
                // Process each certificate
                foreach ($certificates as $certificate) {
                    // Ensure the certificate data is correctly formatted before updating
                    if (isset($certificate['issue_date'], $certificate['start_date'], $certificate['total_leave_days'], $certificate['diagnosis'])) {
                        // Update each certificate record here (you might want to customize how you update)
                        $this->db->table('tbl_certificate')
                            ->where('appointment_id', $appointment_id)
                            ->update([
                                'issue_date' => $certificate['issue_date'],
                                'start_date' => $certificate['start_date'],
                                'total_leave_days' => $certificate['total_leave_days'],
                                'diagnosis' => $certificate['diagnosis'],
                                'record_date' => $record_date,  // Include record_date in update
                            ]);
                    } else {
                        log_message('error', 'Invalid certificate data');
                        return $this->failValidationErrors('Invalid certificate data.');
                    }
                }

                return $this->respondCreated([
                    'status'  => true,
                    'message' => 'Certificate updated successfully.',
                ]);
            } catch (\Exception $e) {
                return $this->failServerError('Failed to update certificate: ' . $e->getMessage());
            }
        } else {
            // Record doesn't exist, insert a new one
            try {
                // Process each certificate
                foreach ($certificates as $certificate) {
                    // Ensure the certificate data is correctly formatted before inserting
                    if (isset($certificate['issue_date'], $certificate['start_date'], $certificate['total_leave_days'], $certificate['diagnosis'])) {
                        // Insert each certificate record here (you might want to customize how you insert)
                        $this->db->table('tbl_certificate')
                            ->insert([
                                'appointment_id' => $appointment_id,
                                'issue_date' => $certificate['issue_date'],
                                'start_date' => $certificate['start_date'],
                                'total_leave_days' => $certificate['total_leave_days'],
                                'diagnosis' => $certificate['diagnosis'],
                                'record_date' => $record_date,  // Include record_date in insert
                            ]);
                    } else {
                        log_message('error', 'Invalid certificate data');
                        return $this->failValidationErrors('Invalid certificate data.');
                    }
                }

                return $this->respondCreated([
                    'status'  => true,
                    'message' => 'Certificate saved successfully.',
                ]);
            } catch (\Exception $e) {
                log_message('error', 'Error inserting data: ' . $e->getMessage());
                return $this->failServerError('Failed to save certificate: ' . $e->getMessage());
            }
        }
    }




    public function get_appointment_price($id)
    {
        $model = new HomeModel();
        $result = $model->get_appointment_price_by_id($id);

        if ($result) {
            return $this->response->setJSON([
                'status' => 200,
                'data'   => ['price' => $result->price]
            ]);
        }

        return $this->response->setJSON([
            'status'  => 404,
            'message' => 'Appointment not found'
        ]);
    }


    public function getCompletePatientDetails($appointment_id)
    {
        $patientDetails = $this->db->table('tbl_patient_records')
            ->where('appointment_id', $appointment_id)
            ->get()
            ->getRowArray();

        $prescriptions = $this->db->table('tbl_prescriptions')
            ->where('appointment_id', $appointment_id)
            ->get()
            ->getResultArray();

        $recommendedTests = $this->db->table('tbl_recommended_tests')
            ->where('appointment_id', $appointment_id)
            ->get()
            ->getResultArray();

        $appointmentDetails = $this->db->table('tbl_appointment')
            ->where('id', $appointment_id)
            ->get()
            ->getRowArray();

        $certificateDetails = $this->db->table('tbl_certificate')
            ->where('appointment_id', $appointment_id)
            ->get()
            ->getRowArray();

        return $this->response->setJSON([
            'patientDetails' => $patientDetails,
            'prescriptions' => $prescriptions,
            'recommendedTests' => $recommendedTests,
            'appointmentDetails' => $appointmentDetails,
            'certificateDetails' => $certificateDetails,
        ]);
    }
 
}

