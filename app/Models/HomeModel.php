<?php
namespace App\Models;

use CodeIgniter\Model;

class HomeModel extends Model
{
    protected $table = 'tbl_appointment'; // Replace with your actual table name
    protected $primaryKey = 'id'; // Replace with your actual primary key
    protected $allowedFields = [
        'id', 
        'branch', 
        'hawservices', 
        'consultant', 
        'healthissues', 
        'dates', 
        'full_name', 
        'age', 
        'mobile_no', 
        'email_id', 
        'locations', 
        'slot', 
        'subscription', 
        'plan', 
        'subscriptiontype', 
        'startdate', 
        'section', 
        'created_at', 
        'is_deleted', 
        'payment_status', 
        'appointment_status', 
        'price'
    ]; 
    public function updatePayment($data)
    {
        return $this->update($data['id'], [
            'price' => $data['price'],
            'payment_status' => $data['payment_status'],
            'appointment_status' => $data['appointment_status'] 
        ]);
    }
    public function getalldata($table, $wherecond)
    {
        $result = $this->db->table($table)->where($wherecond)->get()->getResult();
        if ($result) {
            return $result;
        } else {
            return false;
        }
    }
    public function getidbybranch($branch_id, $section_id)
    {
        // Casting branch_id and section_id to text if columns are of text type
        $query = "SELECT DISTINCT doctor_id
                  FROM tbl_slots
                  WHERE branch_id::integer = '$branch_id' AND section::character = '$section_id';";
        
        $result = $this->db->query($query)->getResultArray();
        
        $doctor_ids = array_column($result, 'doctor_id');
        
        if ($doctor_ids) {
            return $doctor_ids;
        } else {
            return false;
        }
    }
    public function get_appointment_price_by_id($id)
    {
        $builder = $this->db->table('tbl_appointment');
        $query = $builder->select('price')
            ->where('id', $id)
            ->get();

        return $query->getRow(); // Return the row containing price
    }
    public function getemail($id)
    {
        $builder = $this->db->table('tbl_register');
        $query = $builder->select('email')
            ->where('id', $id)
            ->get();

        return $query->getRow();
    }
    public function getslotstime($slotid)
    {
        $builder = $this->db->table('tbl_slots');
        $query = $builder->select('slots_time')
            ->where('id', $slotid)
            ->get();

        return $query->getRow();
    }
}
