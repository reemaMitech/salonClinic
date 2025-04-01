<?php

namespace App\Controllers;

use DateTime;
require_once ROOTPATH . 'public/JWT/src/JWT.php';

use CodeIgniter\Controller;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Exception;

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

  
public function authenticate()
{
    // Set the content type to JSON
    $this->response->setHeader('Content-Type', 'application/json');

    // Parse the input JSON
    $input = json_decode($this->request->getBody(), true);

    // Validate JSON input format
    if (!$input) {
        return $this->response->setJSON([
            'status' => 400,
            'message' => 'Invalid input format.'
        ])->setStatusCode(400);
    }

    $mobile = trim($input['mobile'] ?? '');
    $password = trim($input['password'] ?? '');

    // Check if required fields are provided
    if (empty($mobile) || empty($password)) {
        return $this->response->setJSON([
            'status' => 400,
            'message' => 'Mobile number and Password are required.'
        ])->setStatusCode(400);
    }

    // Look up the user in the database based on mobile number
    $user = $this->db->table('tbl_register')
             ->where('mobile', $mobile)
             ->where('is_deleted', 'N')

             ->get()
             ->getRowArray();

    // If user is not found or password does not match, return error
    if (!$user || !password_verify($password, $user['password'])) {
        return $this->response->setJSON([
            'status' => 401,
            'message' => 'Invalid mobile number or password.'
        ])->setStatusCode(401);
    }

    // Create payload for JWT token
    $payload = [
        'iat' => time(),
        'exp' => time() + (2 * 60),
        'user_id' => $user['id'],
        'name' => $user['name'],
        'mobile' => $user['mobile'],
        'role' => $user['role']
    ];
    $token = JWT::encode($payload, $this->key, 'HS256');

    // Return JSON response with token
    return $this->response->setJSON([
        'userid' => $user['id'],
        'status' => 200,
        'message' => 'Authentication successful',
        'token' => $token,
        'role' => $user['role']
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
    
//     public function savescedule($table)
// {
//     $input = $this->request->getJSON();
//     print_r($input);die;
//     if (empty($input)) {
//         return $this->response->setJSON([
//             'status' => 400,
//             'message' => 'No data provided.'
//         ])->setStatusCode(400);
//     }

//     $data = json_decode(json_encode($input), true);

//     if (json_last_error() !== JSON_ERROR_NONE) {
//         return $this->response->setJSON([
//             'status' => 400,
//             'message' => 'Invalid JSON input.'
//         ])->setStatusCode(400);
//     }

//     $builderSlots = $this->db->table($table); // Table for slots (tbl_slots)
//     $builderSchedule = $this->db->table('tbl_schedule'); // Table for schedule (tbl_schedule)

//     foreach ($data['schedules'] as $schedule) {
//         $scheduleId = null; // To capture the inserted schedule ID

//         // Check if startTime and endTime exist for inserting into tbl_schedule
//         if (!empty($schedule['startTime']) && !empty($schedule['endTime'])) {
//             $scheduleData = [
//                 'start_time' => $schedule['startTime'],
//                 'end_time' => $schedule['endTime'],
//                 'doctor_id' => $schedule['consultant'],
//                 'branch_id' => $schedule['branch'],
//                 'day_name' => $schedule['day'],
//                 'is_deleted' => 'N',
//                 'created_at' => date('Y-m-d H:i:s') // Insert current timestamp
//             ];

//             if ($builderSchedule->insert($scheduleData)) {
//                 $scheduleId = $this->db->insertID(); // Get the ID of the newly inserted schedule
//             } else {
//                 return $this->response->setJSON([
//                     'status' => 500,
//                     'message' => 'Schedule creation failed for day: ' . $schedule['day']
//                 ])->setStatusCode(500);
//             }
//         }

//         // Insert each slot into the slots table, linking it to the schedule ID
//         foreach ($schedule['slots'] as $slot) {
//             $slotData = [
//                 'slots_time' => $slot,
//                 'day_name' => $schedule['day'],
//                 'branch_id' => $schedule['branch'],
//                 'doctor_id' => $schedule['consultant'],
//                 'schedule_id' => $scheduleId, // Link this slot to the schedule using the schedule_id
//             ];

//             if (!$builderSlots->insert($slotData)) {
//                 return $this->response->setJSON([
//                     'status' => 500,
//                     'message' => 'Slot creation failed for slot: ' . $slot . ' on day: ' . $schedule['day']
//                 ])->setStatusCode(500);
//             }
//         }
//     }

//     return $this->response->setJSON([
//         'status' => 201,
//         'message' => 'Records created successfully'
//     ])->setStatusCode(201);
// }
    
public function savescedule($table)
{
    $input = $this->request->getJSON();
    // print_r($input); die; // For debugging

    // Validate input
    if (empty($input)) {
        return $this->response->setJSON([
            'status' => 400,
            'message' => 'No data provided.'
        ])->setStatusCode(400);
    }

    // Convert JSON to associative array
    $data = json_decode(json_encode($input), true);

    // Validate JSON decoding
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->response->setJSON([
            'status' => 400,
            'message' => 'Invalid JSON input.'
        ])->setStatusCode(400);
    }

    // Prepare database tables for slots and schedule
    $builderSlots = $this->db->table($table); // Table for slots (tbl_slots)
    $builderSchedule = $this->db->table('tbl_schedule'); // Table for schedule (tbl_schedule)
    
    // Loop through schedules to insert data
    foreach ($data['schedules'] as $schedule) {
        $consultantId = $schedule['consultant'];
        $whereCond = ['id' => $consultantId]; 
        $query = $this->db->table('tbl_register')->where($whereCond);
        $consultantData = $query->get()->getRow();

        $section = null;
        if ($consultantData) {
            $section = $consultantData->section; 
        }

        $scheduleId = null; 

        if (!empty($schedule['startTime']) && !empty($schedule['endTime'])) {
            $scheduleData = [
                'start_time' => $schedule['startTime'],
                'end_time' => $schedule['endTime'],
                'doctor_id' => $schedule['consultant'],
                'branch_id' => $schedule['branch'],
                'day_name' => $schedule['day'],
                'section'=>$section,
                'slotcount' =>$schedule['slotcount'],
                'is_deleted' => 'N',
                'created_at' => date('Y-m-d H:i:s') // Insert current timestamp
            ];

            // Insert schedule data into tbl_schedule
            if ($builderSchedule->insert($scheduleData)) {
                $scheduleId = $this->db->insertID(); // Get the ID of the newly inserted schedule
            } else {
                return $this->response->setJSON([
                    'status' => 500,
                    'message' => 'Schedule creation failed for day: ' . $schedule['day']
                ])->setStatusCode(500);
            }
        }

        // Insert each slot into the slots table, linking it to the schedule ID
        foreach ($schedule['slots'] as $slot) {
            $slotData = [
                'slots_time' => $slot,
                'day_name' => $schedule['day'],
                'branch_id' => $schedule['branch'],
                'section'=>$section,
                'slotcount' =>$schedule['slotcount'],
                'Price' =>$schedule['Price'],
                'doctor_id' => $schedule['consultant'],
                'schedule_id' => $scheduleId, // Link this slot to the schedule using the schedule_id
            ];

            if (!$builderSlots->insert($slotData)) {
                return $this->response->setJSON([
                    'status' => 500,
                    'message' => 'Slot creation failed for slot: ' . $slot . ' on day: ' . $schedule['day']
                ])->setStatusCode(500);
            }
        }
    }

    // Return success message
    return $this->response->setJSON([
        'status' => 201,
        'message' => 'Records created successfully'
    ])->setStatusCode(201);
}

    public function create($table)
{
    $input = $this->request->getJSON();

    // echo "<pre>";print_r($input);exit();
    if (empty($input)) {
        return $this->response->setJSON([
            'status' => 400,
            'message' => 'No data provided.'
        ])->setStatusCode(400);
    }
    $builder = $this->db->table($table);
    $builder->set((array)$input);
    if ($builder->insert()) {
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

    public function read($table, $id = null)
    {
        try {
            if ($id) {
                $result = $this->db->table($table)
                                   ->where('id', $id)
                                   ->where('is_deleted', 'N') 
                                   ->get()
                                   ->getRowArray();
            } else {
                $result = $this->db->table($table)
                                   ->where('is_deleted', 'N') 
                                   ->get()
                                   ->getResultArray();
            }
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
     // UPDATE record
        public function update($table, $id)
        {
            $input = json_decode(file_get_contents('php://input'), true);
            // print_r($input);die;
            if (!is_array($input) || empty($input)) {
                return $this->response->setJSON([
                    'status' => 400,
                    'message' => 'No valid input data provided'
                ])->setStatusCode(400);
            }
            if (isset($input[0]) && is_array($input[0])) {
                $input = $input[0]; 
            }
            $validFields = $this->getTableColumns($table);
            $updateData = [];
            foreach ($validFields as $field) {
                if (isset($input[$field])) {
                    $updateData[$field] = $input[$field];
                }
            }
            if (empty($updateData)) {
                return $this->response->setJSON([
                    'status' => 400,
                    'message' => 'No valid fields provided for update'
                ])->setStatusCode(400);
            }
            if ($this->db->table($table)->update($updateData, ['id' => $id])) {
                return $this->response->setJSON([
                    'status' => 200,
                    'message' => 'Record updated successfully'
                ])->setStatusCode(200);
            }
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'Record update failed'
            ])->setStatusCode(500);
        }
        private function getTableColumns($table)
        {
            return $this->db->getFieldNames($table);
        }
        public function delete($table, $id)
        {
            $updateData = ['is_deleted' => 'Y'];
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

// public function fetchslots()
// {
//     $rawInput = file_get_contents('php://input');
//     $input = json_decode($rawInput, true); 

//     $selected_date = $input['selected_date'];
//     $doctor_id = $input['doctor_id'];
//     $branch_id = $input['branch_id'];
   
//     $day_name = DateTime::createFromFormat('Y-m-d', $selected_date)->format('l');

//     $holiday_builder = $this->db->table('tbl_holiday');
//     $holiday_builder->where([
//         'date' => $selected_date,
//         'is_deleted' => 'N'
//     ]);
//     $holiday = $holiday_builder->get()->getRow();

//     if ($holiday) {
//         return $this->response->setJSON([
//             'status' => 203,
//             'message' => 'No slots available on this date as it is a holiday.'
//         ])->setStatusCode(200);
//     }

//     $slots_builder = $this->db->table('tbl_slots');
//     $slots_builder->where([
//         'doctor_id' => $doctor_id,
//         'branch_id' => $branch_id,
//         'day_name' => $day_name,
//         'is_deleted' => 'N'
//     ]);
//     $slots = $slots_builder->get()->getResult();

//     $booked_slots_builder = $this->db->table('tbl_booked_slots');
//     $booked_slots_builder->select('slots_id');
//     $booked_slots_builder->where([
//         'doctor_id' => $doctor_id,
//         'branch_id' => $branch_id,
//         'selected_date' => $selected_date,
//         'is_deleted' => 'N'
//     ]);
//     $booked_slots = $booked_slots_builder->get()->getResultArray();
//     $booked_slots_ids = array_column($booked_slots, 'slots_id');

//     if (!empty($booked_slots_ids)) {
//         $slots = array_filter($slots, function($slot) use ($booked_slots_ids) {
//             return !in_array($slot->id, $booked_slots_ids);
//         });
//     }

//     return $this->response->setJSON([
//         'status' => 200,
//         'data' => array_values($slots)
//     ])->setStatusCode(200);
// }
public function fetchslots()
{
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true); 
    $selected_date = $input['selected_date'];
    $doctor_id = $input['doctor_id'];
    $branch_id = $input['branch_id'];
    $day_name = DateTime::createFromFormat('Y-m-d', $selected_date)->format('l');
    $timezone = new DateTimeZone('Asia/Kolkata');
    $current_time = new DateTime('now', $timezone);
    $current_date = $current_time->format('Y-m-d');
    $is_today = ($selected_date === $current_date);

    $holiday_builder = $this->db->table('tbl_holiday');
    $holiday_builder->where([
        'date' => $selected_date,
        'is_deleted' => 'N'
    ]);
    $holiday = $holiday_builder->get()->getRow();

    if ($holiday) {
        return $this->response->setJSON([
            'status' => 203,
            'message' => 'No slots available on this date as it is a holiday.'
        ])->setStatusCode(200);
    }

    $slots_builder = $this->db->table('tbl_slots');
    $slots_builder->where([
        'doctor_id' => $doctor_id,
        'branch_id' => $branch_id,
        'day_name' => $day_name,
        'is_deleted' => 'N'
    ]);
    $slots = $slots_builder->get()->getResult();

    $booked_slots_builder = $this->db->table('tbl_booked_slots');
    $booked_slots_builder->select('slots_id');
    $booked_slots_builder->where([
        'doctor_id' => $doctor_id,
        'branch_id' => $branch_id,
        'selected_date' => $selected_date,
        'is_deleted' => 'N'
    ]);
    $booked_slots = $booked_slots_builder->get()->getResultArray();
    $booked_slots_ids = array_column($booked_slots, 'slots_id');

    $filtered_slots = array_filter($slots, function($slot) use ($booked_slots_ids, $is_today, $current_time) {
        $slot_time = DateTime::createFromFormat('g:i A', $slot->slots_time, new DateTimeZone('Asia/Kolkata'));
        if ($is_today && $slot_time <= $current_time) {
            return false; 
        }
        return !in_array($slot->id, $booked_slots_ids);
    });

    return $this->response->setJSON([
        'status' => 200,
        'data' => array_values($filtered_slots)
    ])->setStatusCode(200);
}

        public function getsections()
        {
            try {
                $builder = $this->db->table('tbl_register')
                    ->select('tbl_register.id as con_id, tbl_register.degree, tbl_register.role, tbl_register.name, tbl_register.section, tbl_section.section_name') // Selecting the section_name
                    ->join('tbl_section', 'tbl_section.id = tbl_register.section', 'left') // Join to get section_name
                    ->where('tbl_register.is_deleted', 'N')
                    ->where('tbl_register.role', 'Consultant');

                
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
                ->where('tbl_register.role', 'Employee')
                ->orWhere('tbl_register.role', 'Admin')
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
                // $builder = $this->db->table($table)->where('is_deleted', 'N');
                $builder = $this->db->table($table)->whereIn('is_deleted', ['N', 'Y']);

                if ($consultantId) {
                    $builder->where('doctor_id', intval($consultantId));
                }
                
                if ($branchId) {
                    $builder->where('branch_id', intval($branchId));
                }
        
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
                    ->select("$table.*, tbl_branch.branch_name, tbl_register.name as consultant_name")
                    ->where("$table.is_deleted", 'N')
                    ->join('tbl_branch', "tbl_branch.id = $table.branch", 'left')
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
            $date = $this->request->getPost('date') ?? $this->request->getJSON()->date; // Extract date
            $serviceId = $this->request->getPost('serviceId') ?? $this->request->getJSON()->serviceId; // Extract service ID
            
            log_message('info', 'Fetching appointments with parameters: consultantId={consultantId}, branchId={branchId}, tab={tab}, date={date}, serviceId={serviceId}', [
                'consultantId' => $consultantId,
                'branchId' => $branchId,
                'tab' => $tab,
                'date' => $date,
                'serviceId' => $serviceId
            ]);
        
            try {
                $builder = $this->db->table($table)
                    ->select("$table.*, tbl_branch.branch_name, tbl_register.name as consultant_name, appointment_status")
                    ->where("$table.is_deleted", 'N')
                    ->join('tbl_branch', "tbl_branch.id = $table.branch", 'left')
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
        
                // Filter by date
                if ($date) {
                    $builder->where("$table.dates", $date);
                }
        
                // Filter by service ID
                if ($serviceId) {
                    $builder->where("$table.section", intval($serviceId));
                }
        
                // Filter by tab name with modified logic for the 'home' tab
                $currentDate = date('Y-m-d');
                switch ($tab) {
                    case 'home':
                        // Home tab: Include all statuses (PE, CN, CD) without filtering by a specific status
                        $builder->whereIn('appointment_status', ['PE', 'CN', 'CD']);
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
        
                    default:
                        // Default to empty results if tab name is invalid
                        $builder->where('appointment_status', 'INVALID');
                }
        
                // Execute query and fetch results
                $result = $builder->get()->getResultArray();
        
                // Log the last executed SQL query
                $lastQuery = $this->db->getLastQuery();
                log_message('info', 'Last Executed Query: {lastQuery}', ['lastQuery' => $lastQuery]);
        
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
            // Define the directory to store resumes
            $resumeDirectory = 'public/assets/resume/';
        
            // Get the ID from the URI segment (assuming it's the 3rd segment)
            $id = $this->uri->getSegment(3);
        
            // Check if an existing record is being updated
            $existingData = null;
            if (!empty($id)) {
                // Fetch the existing data for this employee
                $existingData = $this->db->table('tbl_register')->where('id', $id)->get()->getRowArray();
            }
        
            // Initialize the data array with basic information
            $data = [
                'name' => $_POST['name'],
                'section' => $_POST['section'],
                'mobile' => $_POST['mobile'],
                'email' => $_POST['email'],
                'address' => $_POST['address'],
                'password' => password_hash($_POST['password'], PASSWORD_BCRYPT),
                'joining_date' => $_POST['joiningDate'],
                'role' => $_POST['role'],
                'access_levels' => $_POST['accessLevels']
            ];
        
            // Check if a resume file is uploaded
            if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
                // Process the uploaded file
                $fileName = $_FILES['resume']['name'];
                $tempPath = $_FILES['resume']['tmp_name'];
                $targetPath = $resumeDirectory . $fileName;
        
                if (move_uploaded_file($tempPath, $targetPath)) {
                    // Add resume file name to data array
                    $data['resume'] = $fileName;
                } else {
                    // Return error if the file couldn't be moved
                    return $this->response->setJSON([
                        'status' => 'error',
                        'message' => 'Failed to upload the resume file.'
                    ]);
                }
            } else {
                // If no file was uploaded, retain the existing resume if updating an existing record
                if ($existingData && !empty($existingData['resume'])) {
                    $data['resume'] = $existingData['resume'];
                }
            }
        
            // Check if the ID is provided for an update
            if (!empty($id)) {
                // Update the existing record
                $this->db->table('tbl_register')->where('id', $id)->update($data);
                $message = 'Employee record updated successfully!';
            } else {
                // Insert a new record
                $this->db->table('tbl_register')->insert($data);
                $message = 'Employee record and resume saved successfully!';
            }
        
            // Return a JSON response
            return $this->response->setJSON([
                'status' => 'success',
                'message' => $message
            ]);
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
                $slots = array_filter($slots, function($slot) use ($booked_slots_ids) {
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
                return $this->response->setJSON(['status' => 'success', 'message' => 'Database connection established successfully.']);
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

//     public function get_appointment_data($table, $id = null)
// {
//     try {
//         // Assuming tbl_appointment has consultant_id, branch_id, and services_id columns
//         if ($id) {
//             $result = $this->db->table($table)
//                 // ->select('tbl_appointment.*, tbl_consultants.name AS cname, tbl_branch.name AS branch_name, tbl_section.section_name AS sectionname')
//                 ->select('tbl_appointment.*, tbl_consultants.name AS cname, tbl_branch.branch_name AS branch_name, tbl_slots.slots_time AS slots_time, tbl_slots.day_name AS day_name ')

//                 ->join('tbl_consultants', 'tbl_consultants.id = tbl_appointment.consultant', 'left')
//                 ->join('tbl_branch', 'tbl_branch.id = tbl_appointment.branch', 'left')
//                 ->join('tbl_slots', 'tbl_slots.id = tbl_appointment.slot', 'left')

//                 // ->join('tbl_section', 'tbl_section.id = tbl_appointment.hawservices', 'left')
//                 ->where('tbl_appointment.id', $id)
//                 ->where('tbl_appointment.is_deleted', 'N') 
//                 ->get()
//                 ->getRowArray();
//         } else {
//             $result = $this->db->table($table)
//             ->select('tbl_appointment.*, tbl_consultants.name AS cname, tbl_branch.branch_name AS branch_name, tbl_slots.slots_time AS slots_time, tbl_slots.day_name AS day_name ')
//             ->join('tbl_consultants', 'tbl_consultants.id = tbl_appointment.consultant', 'left')
//                 ->join('tbl_branch', 'tbl_branch.id = tbl_appointment.branch', 'left')
//                 ->join('tbl_slots', 'tbl_slots.id = tbl_appointment.slot', 'left')

//                 // ->join('tbl_section', 'tbl_section.id = tbl_appointment.hawservices', 'left')
//                 ->where('tbl_appointment.is_deleted', 'N') 
//                 ->get()
//                 ->getResultArray();
//         }

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

public function get_todays_appointment_data($table, $id = null)
{
    try {
        // Get the current date
        $currentDate = date('Y-m-d'); // This assumes the 'date' column is in 'Y-m-d' format
        
        if ($id) {
            $result = $this->db->table($table)
                ->select('tbl_appointment.*, tbl_register.name AS cname, tbl_branch.branch_name AS branch_name, tbl_slots.slots_time AS slots_time, tbl_slots.day_name AS day_name, tbl_section.section_name AS section_name, tbl_slots.Price')
                ->join('tbl_register', 'tbl_register.id = tbl_appointment.consultant', 'left')
                ->join('tbl_branch', 'tbl_branch.id = tbl_appointment.branch', 'left')
                ->join('tbl_slots', 'tbl_slots.id = CAST(tbl_appointment.slot AS INTEGER)', 'left')
                ->join('tbl_section', 'tbl_section.id = tbl_appointment.section', 'left')

                ->where('tbl_appointment.id', $id)

                ->where('tbl_appointment.appointment_status', 'PE')

                ->where('tbl_appointment.is_deleted', 'N')
                ->where('CAST(tbl_appointment.dates AS DATE) =', $currentDate)
                ->get()
                ->getRowArray();
        } else {
            $result = $this->db->table($table)
            ->select('tbl_appointment.*, tbl_register.name AS cname, tbl_branch.branch_name AS branch_name, tbl_slots.slots_time AS slots_time, tbl_slots.day_name AS day_name, tbl_section.section_name AS section_name, tbl_slots.Price')
            ->join('tbl_register', 'tbl_register.id = tbl_appointment.consultant', 'left')
                ->join('tbl_branch', 'tbl_branch.id = tbl_appointment.branch', 'left')
                ->join('tbl_slots', 'tbl_slots.id = CAST(tbl_appointment.slot AS INTEGER)', 'left')
                ->join('tbl_section', 'tbl_section.id = tbl_appointment.section', 'left')

                ->where('tbl_appointment.is_deleted', 'N')
                ->where('tbl_appointment.appointment_status', 'PE')
                ->where('CAST(tbl_appointment.dates AS DATE) =', $currentDate)
                ->get()
                ->getResultArray();
        }

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
// public function slotsmanage()
// {
//     // Retrieve raw input and decode it
//     $rawInput = file_get_contents('php://input');
//     $input = json_decode($rawInput, true);
//     if (empty($input)) {
//         return $this->response->setJSON([
//             'status' => 400,
//             'message' => 'No data provided.'
//         ])->setStatusCode(400);
//     }
//     if ($input['status'] === 'B') {
//         $data = [
//             'doctor_id' => $input['consultantId'] ?? null,
//             'status' => $input['status'],
//             'branch_id' => $input['branchId'] ?? null,
//             'selected_date' => $input['date'] ?? null,
//             'slots_id' => $input['id'] ?? null 
//         ];

//         $builder = $this->db->table('tbl_booked_slots');
//         if ($builder->insert($data)) {
//             return $this->response->setJSON([
//                 'status' => 200,
//                 'message' => 'Record created successfully'
//             ])->setStatusCode(200);
//         }
//         return $this->response->setJSON([
//             'status' => 500,
//             'message' => 'Failed to create record'
//         ])->setStatusCode(500);

//     } elseif ($input['status'] === 'D') {
//         $updateData = ['is_deleted' => 'Y'];
//         $id = $input['id'] ?? null;
//         if ($this->db->table('tbl_slots')->where('id', $id)->update($updateData)) {
//             return $this->response->setJSON([
//                 'status' => 200,
//                 'message' => 'Record marked as deleted successfully'
//             ])->setStatusCode(200);
//         }
//         return $this->response->setJSON([
//             'status' => 500,
//             'message' => 'Failed to mark the record as deleted'
//         ])->setStatusCode(500);
//     }
//     return $this->response->setJSON([
//         'status' => 400,
//         'message' => 'Invalid status value'
//     ])->setStatusCode(400);
// }

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
    // Query for fetching conducted appointments
    $builder = $this->db->table('tbl_appointment');
    $builder->select([
        'tbl_appointment.*',
        'tbl_slots.slots_time AS slot_time',
        'tbl_branch.branch_name AS branch_name',
        'tbl_register.name AS consultant_name',
        'tbl_section.section_name AS section_name'
    ]); // Adjust fields as needed

    $builder->join('tbl_slots', 'CAST(tbl_appointment.slot AS INTEGER) = tbl_slots.id', 'left');
    $builder->join('tbl_branch', 'tbl_appointment.branch = tbl_branch.id', 'left');
    $builder->join('tbl_register', 'tbl_appointment.consultant = tbl_register.id', 'left');
    $builder->join('tbl_section', 'tbl_appointment.section = tbl_section.id', 'left');
    
    $builder->where('tbl_appointment.appointment_status', 'CD'); // Only conducted appointments
    
    $query = $builder->get();
    $data = $query->getResultArray();

    return $this->response->setJSON(['status' => 200, 'data' => $data]);
}

public function getUpcomingAppointments()
{
    // Get the current date in the required format (Y-m-d)
    $currentDate = date('Y-m-d');

    // Query for fetching upcoming appointments
    $builder = $this->db->table('tbl_appointment');
    $builder->select([
        'tbl_appointment.*',
        'tbl_slots.slots_time AS slot_time',
        'tbl_branch.branch_name AS branch_name',
        'tbl_register.name AS consultant_name',
        'tbl_section.section_name AS section_name'
    ]);

    // Cast tbl_appointment.slot to integer for comparison with tbl_slots.id
    $builder->join('tbl_slots', 'CAST(tbl_appointment.slot AS INTEGER) = tbl_slots.id', 'left');
    $builder->join('tbl_branch', 'tbl_appointment.branch = tbl_branch.id', 'left');
    $builder->join('tbl_register', 'tbl_appointment.consultant = tbl_register.id', 'left');
    $builder->join('tbl_section', 'tbl_appointment.section = tbl_section.id', 'left');

    // Condition for upcoming appointments and current day or after
    $builder->where('tbl_appointment.appointment_status', 'PE'); // Only upcoming appointments
    $builder->where('tbl_appointment.dates >=', $currentDate); // Only dates today or after

    $query = $builder->get();
    $data = $query->getResultArray();

    return $this->response->setJSON(['status' => 200, 'data' => $data]);
}


// public function getCancelledAppointments()
// {
//     // Query for fetching upcoming appointments
//     $builder = $this->db->table('tbl_appointment');
//     $builder->select([
//         'tbl_appointment.*',
//         'tbl_slots.slots_time AS slot_time',
//         'tbl_branch.branch_name AS branch_name',
//         'tbl_consultants.name AS consultant_name',
//         'tbl_section.section_name AS section_name'
//     ]); // Adjust fields as needed

//     $builder->join('tbl_slots', 'tbl_appointment.slot = tbl_slots.id', 'left');
//     $builder->join('tbl_branch', 'tbl_appointment.branch = tbl_branch.id', 'left');
//     $builder->join('tbl_consultants', 'tbl_appointment.consultant = tbl_consultants.id', 'left');
//     $builder->join('tbl_section', 'tbl_appointment.section = tbl_section.id', 'left');
    
//     $builder->where('tbl_appointment.appointment_status', 'CN'); // Only cancelled appointments
    
//     $query = $builder->get();
//     $data = $query->getResultArray();

//     return $this->response->setJSON(['status' => 200, 'data' => $data]);
// }

public function getCancelledAppointments()
{
    // Query for fetching upcoming appointments
    $builder = $this->db->table('tbl_appointment');
    $builder->select([
        'tbl_appointment.*',
        'tbl_slots.slots_time AS slot_time',
        'tbl_branch.branch_name AS branch_name',
        'tbl_register.name AS consultant_name',
        'tbl_section.section_name AS section_name'
    ]); // Adjust fields as needed

    $builder->join('tbl_slots', 'CAST(tbl_appointment.slot AS INTEGER) = tbl_slots.id', 'left');
    $builder->join('tbl_branch', 'tbl_appointment.branch = tbl_branch.id', 'left');
    $builder->join('tbl_register', 'tbl_appointment.consultant = tbl_register.id', 'left');
    $builder->join('tbl_section', 'tbl_appointment.section = tbl_section.id', 'left');
    
    $builder->where('tbl_appointment.appointment_status', 'CN'); // Only cancelled appointments
    
    $query = $builder->get();
    $data = $query->getResultArray();

    return $this->response->setJSON(['status' => 200, 'data' => $data]);
}

public function getPendingAppointments()
{
    // Get today's date in the 'Y-m-d' format
    $today = date('Y-m-d');
    
    // Query for fetching previous or today's pending appointments
    $builder = $this->db->table('tbl_appointment');
    $builder->select([
        'tbl_appointment.*',
        'tbl_slots.slots_time AS slot_time',
        'tbl_slots.Price',
        'tbl_branch.branch_name AS branch_name',
        'tbl_register.name AS consultant_name',
        'tbl_section.section_name AS section_name'
    ]);

    // Join necessary tables
    $builder->join('tbl_slots', 'CAST(tbl_appointment.slot AS INTEGER) = tbl_slots.id', 'left');
    $builder->join('tbl_branch', 'tbl_appointment.branch = tbl_branch.id', 'left');
    $builder->join('tbl_register', 'tbl_appointment.consultant = tbl_register.id', 'left');
    $builder->join('tbl_section', 'tbl_appointment.section = tbl_section.id', 'left');

    // Conditions for fetching pending appointments up to and including today
    $builder->where('tbl_appointment.appointment_status', 'PE');
    $builder->where('tbl_appointment.dates <', $today); // Appointments up to and including today

    $query = $builder->get();
    $data = $query->getResultArray();

    return $this->response->setJSON(['status' => 200, 'data' => $data]);
}



// public function fetchslotsforcustome()
// {
//     $rawInput = file_get_contents('php://input');
//     $input = json_decode($rawInput, true); 
//     if (empty($input['branch_id'])) {
//         return $this->response->setJSON([
//             'status' => 400,
//             'message' => 'Required fields are missing.'
//         ])->setStatusCode(400);
//     }

//     $branch_id = $input['branch_id'];

//     // Fetch all slots for the specified branch without date filtering
//     $slots_builder = $this->db->table('tbl_exerciseslot');
//     $slots_builder->where([
//         'branch_id' => $branch_id,
//         'is_deleted' => 'N'
//     ]);
//     $slots = $slots_builder->get()->getResult();

//     // Fetch booked slots for the specified branch
//     $booked_slots_builder = $this->db->table('tbl_exercisebookedslot');
//     $booked_slots_builder->select('slots_id');
//     $booked_slots_builder->where([
//         'branch_id' => $branch_id,
//         'is_deleted' => 'N'
//     ]);
//     $booked_slots = $booked_slots_builder->get()->getResultArray();
//     $booked_slots_ids = array_column($booked_slots, 'slots_id');

//     // Filter out booked slots
//     if (!empty($booked_slots_ids)) {
//         $slots = array_filter($slots, function($slot) use ($booked_slots_ids) {
//             return !in_array($slot->id, $booked_slots_ids);
//         });
//     }

//     return $this->response->setJSON([
//         'status' => 200,
//         'data' => array_values($slots)
//     ])->setStatusCode(200); 
// }

public function fetchslotsforcustome()
{
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

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
    $filtered_slots = array_filter($slots, function($slot) use ($booked_slots_count, $selected_date) {
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
  public function get_where_condition_data($table, $role)
{
    // echo "hiii";
    // echo $role; // This should now output the role value
    // exit();

    try {
        $builder = $this->db->table($table);

        // Apply the custom where condition if provided
        if (!empty($role)) {
            $builder->where('role', $role);
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

public function submitappointment()
{
    date_default_timezone_set('Asia/Kolkata');
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    $date = isset($input['date']) ? (new DateTime($input['date']))->format('Y-m-d') : null;
    $startDate = isset($input['startDate']) ? (new DateTime($input['startDate']))->format('Y-m-d') : null;

    if ($date && !strtotime($date)) {
        return $this->response->setJSON(['status' => 400, 'message' => 'Invalid date format for date.'])->setStatusCode(400);
    }
    if ($startDate && !strtotime($startDate)) {
        return $this->response->setJSON(['status' => 400, 'message' => 'Invalid date format for start date.'])->setStatusCode(400);
    }
    if (empty($input)) {
        return $this->response->setJSON(['status' => 400, 'message' => 'No data provided.'])->setStatusCode(400);
    }

    $this->db->transBegin();

    try {
        if (!empty($input['consultant'])) {
            $appointmentQuery = "INSERT INTO tbl_appointment 

                (branch, hawservices, consultant, healthissues, dates, full_name, age, mobile_no, email_id, locations, slot, subscription, plan, subscriptionType, startDate, section,appointment_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,'PE') RETURNING id";


            $query = $this->db->query($appointmentQuery, [
                $input['branch'], $input['hawservices'], $input['consultant'], $input['healthissues'], $date,
                $input['full_name'], $input['age'], $input['mobile_no'], $input['email_id'], $input['location'], 
                $input['slot'], $input['subscription'], $input['plan'], $input['subscriptionType'], 
                $startDate, $input['section']
            ]);

            if (!$query) {
                throw new Exception('Error creating single appointment.');
            }

            $appointmentId = $query->getRow()->id ?? $this->db->insertID();

            $bookedSlotQuery = "INSERT INTO tbl_booked_slots 
                (slots_id, doctor_id, status, branch_id, selected_date, ap_id)
                VALUES (?, ?, 'B', ?, ?, ?)";
            
            $this->db->query($bookedSlotQuery, [
                $input['slot'], $input['consultant'], $input['branch'], $date, $appointmentId
            ]);

            $this->db->transCommit();
            return $this->response->setJSON(['status' => 201, 'message' => 'Single appointment created successfully'])->setStatusCode(201);
        }
    
        elseif ($input['plan'] === 'daily' && !empty($input['subscriptionType'])) {
            // print_r($input);
            // die();
        
            preg_match('/\d+/', $input['subscriptionType'], $matches);
            $numDays = isset($matches[0]) ? (int)$matches[0] : 0;
        
            if ($numDays > 0 && isset($input['startDate'])) {
                $startDate = new DateTime($input['startDate']);
                $appointmentIds = [];
        
                for ($i = 0; $i < $numDays; $i++) {
                    $currentDate = clone $startDate;
                    $currentDate->modify("+$i days");
        
                    // Check if any of the fields are empty, and set them to null if they are
                    $hawservices = !empty($input['hawservices']) ? $input['hawservices'] : null;
                    $consultant = !empty($input['consultant']) ? $input['consultant'] : null;
                    $healthissues = !empty($input['healthissues']) ? $input['healthissues'] : null;
                    $subscription = !empty($input['subscription']) ? $input['subscription'] : null;
        
                    // Prepare the insert query
                    $appointmentQuery = "INSERT INTO tbl_appointment 
                        (branch, hawservices, consultant, healthissues, dates, full_name, age, mobile_no, email_id, locations, slot, subscription, plan, subscriptiontype, startDate, section, appointment_status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PE')";
        

                    print_r($appointmentQuery);die;
                    $query = $this->db->query($appointmentQuery, [
                        $input['branch'], $hawservices, $consultant, $healthissues, 
                        $currentDate->format('Y-m-d'), $input['full_name'], $input['age'], $input['mobile_no'], 
                        $input['email_id'], $input['location'], $input['slot'], $subscription, 
                        $input['plan'], $input['subscriptionType'], $startDate->format('Y-m-d'), $input['section']
                    ]);
        
                    if (!$query) {
                        // Print the database error message
                        $dbError = $this->db->error();
                        print_r($dbError);
                        die();
                    }
        
                    $appointmentId = $this->db->insertID();
                    $bookedSlotQuery = "INSERT INTO tbl_exercisebookedslot 
                        (slots_id, status, branch_id, selected_date, ap_id)
                        VALUES (?, 'B', ?, ?, ?)";
                    
                    $this->db->query($bookedSlotQuery, [
                        $input['slot'], $input['branch'], $currentDate->format('Y-m-d'), $appointmentId
                    ]);
        
                    $appointmentIds[] = $appointmentId;
                }
        
                $this->db->transCommit();
                return $this->response->setJSON(['status' => 201, 'message' => 'Records created successfully for ' . $numDays . ' days'])->setStatusCode(201);
            } else {
                throw new Exception('Invalid subscription or start date for daily plan.');
            }
        }
     
       
        throw new Exception('Invalid plan or missing required fields.');
    } 
    catch (Exception $e) {
        $this->db->transRollback();
        return $this->response->setJSON(['status' => 500, 'message' => 'Error: ' . $e->getMessage()])->setStatusCode(500);
    }
}

 public function submitappointments()
{
    date_default_timezone_set('Asia/Kolkata');
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
// print_r($input);die;
    if ($input['plan'] === 'custom' && !empty($input['subscriptionType'])) {
        preg_match('/\d+/', $input['subscriptionType'], $matches);
        $numOccurrences = isset($matches[0]) ? (int)$matches[0] : 0; 

        if ($numOccurrences > 0 && isset($input['startDate']) && isset($input['slots'])) {
            $startDate = new DateTime($input['startDate']); 
            $daysToBook = array_keys($input['slots']);
            $bookedDates = [];

        
            $this->db->transStart();

            try {
                $bookedCount = 0; 

                while ($bookedCount < $numOccurrences) {
                    foreach ($daysToBook as $dayName) {
                        if ($bookedCount >= $numOccurrences) break;
                        $appointmentDate = clone $startDate;

                        while ($appointmentDate->format('l') !== $dayName) {
                            $appointmentDate->modify('next ' . $dayName); // Move to the next Monday/Wednesday
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
                                    'consultant' => !empty($input['consultant']) ? $input['consultant'] : null,
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

                // Commit transaction
                $this->db->transComplete();

                if ($this->db->transStatus() === false) {
                    throw new \Exception('Transaction failed');
                }

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
    // Retrieve JSON data from the request
    $data = $this->request->getJSON(true); // Passing `true` converts JSON to an associative array

    // Validate required fields
    if (!isset($data['id']) || !isset($data['price']) || !isset($data['payment_status'])||!isset($data['appointment_status'])) {
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


// public function readAppointments($id = null)
// {
//     try {
//         if ($id) {
//             // If an ID is provided, join with tbl_section, tbl_register, and tbl_slots to fetch related data
//             $result = $this->db->table('tbl_appointment')
//                                ->join('tbl_section', 'tbl_appointment.section = tbl_section.id', 'left') // Join tbl_section
//                                ->join('tbl_register', 'tbl_appointment.consultant = tbl_register.id', 'left') // Join tbl_register
//                                ->join('tbl_slots', 'CAST(tbl_appointment.slot AS INTEGER) = tbl_slots.id', 'left') // Cast slot to integer
//                                ->where('tbl_appointment.id', $id)
//                                ->where('tbl_appointment.is_deleted', 'N')
//                                ->select('tbl_appointment.*, tbl_section.section_name AS section_name, tbl_register.name AS consultant_name, tbl_slots.slots_time AS slots_time') // Select the necessary columns
//                                ->get()
//                                ->getRowArray();
//         } else {
//             // If no ID is provided, fetch all records with the joined data
//             $result = $this->db->table('tbl_appointment')
//                                ->join('tbl_section', 'tbl_appointment.section = tbl_section.id', 'left') // Join tbl_section
//                                ->join('tbl_register', 'tbl_appointment.consultant = tbl_register.id', 'left') // Join tbl_register
//                                ->join('tbl_slots', 'CAST(tbl_appointment.slot AS INTEGER) = tbl_slots.id', 'left') // Cast slot to integer
//                                ->where('tbl_appointment.is_deleted', 'N')
//                                ->select('tbl_appointment.*, tbl_section.section_name AS section_name, tbl_register.name AS consultant_name, tbl_slots.slots_time AS slots_time') // Select the necessary columns
//                                ->get()
//                                ->getResultArray();
//         }

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
public function readAppointments($id = null)
{
    try {
        $db = \Config\Database::connect();
        if ($id) {
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
        // No need to decode the token if you're getting the user ID directly
        // You can directly use the passed $userId or token data if you need
        
        // Fetch user details from the database using the passed $userId
        $user = $this->db->table('tbl_register')
                 ->where('id', $userId)
                 ->get()
                 ->getRowArray();

        // Return user details in JSON format
        if ($user) {
            return $this->response->setJSON([
                'status' => 200,
                'user' => $user
            ])->setStatusCode(200);
        } else {
            return $this->response->setJSON([
                'status' => 404,
                'message' => 'User not found'
            ])->setStatusCode(404);
        }

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






}

