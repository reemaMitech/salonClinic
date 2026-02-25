<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->post('createemployee', 'Home::createemployee');
$routes->options('(:any)', 'Home::optionsMethod');
$routes->post('savescedule/(:segment)', 'Home::savescedule/$1');
$routes->post('submitappointments', 'Home::submitappointments');

$routes->get('createemployee', 'Home::createemployee');
$routes->post('createemployee/(:segment)', 'Home::createemployee/$1');


$routes->post('create/(:segment)', 'Home::create/$1');
$routes->post('read/(:segment)/(:num)', 'Home::read/$1/$2');

$routes->get('get_where_condition_data/(:segment)/(:any)', 'Home::get_where_condition_data/$1/$2');

$routes->post('createConsultant/(:segment)/(:num)', 'Home::createConsultant/$1/$2');
// $routes->post('createcunsltant/(:segment)/(:any?)', 'Home::createcunsltant/$1/$2');
$routes->post('createLoginEntry', 'Home::createLoginEntry');


$routes->get('get_upcoming_appointments/(:segment)/(:num)', 'Home::getUpcomingAppointments/$1/$2');
$routes->get('get_todays_appointment_data/(:segment)/(:num)', 'Home::get_todays_appointment_data/$1/$2');

// $routes->post('createcunsltant', 'Home::createcunsltant');


$routes->post('read/(:segment)', 'Home::read/$1'); 
$routes->post('delete/(:segment)/(:num)', 'Home::delete/$1/$2'); 
$routes->post('delete/(:segment)', 'Home::read/$1'); 

$routes->post('canceledshedule/(:segment)/(:num)', 'Home::canceledshedule/$1/$2'); 
$routes->post('canceledshedule/(:segment)', 'Home::canceledshedule/$1'); 

$routes->post('update/(:segment)/(:num)', 'Home::update/$1/$2');
$routes->post('update/(:segment)', 'Home::update/$1');
$routes->post('fetchslots', 'Home::fetchslots');

$routes->post('authenticate', 'Home::authenticate');
$routes->get('authenticate', 'Home::authenticate');

$routes->post('verify-token', 'Home::verifyToken'); 

$routes->get('getsections', 'Home::getsections');
$routes->get('getempy/(:segment)', 'Home::getempy/$1');


$routes->post('getslots/(:segment)/(:num)', 'Home::getslots/$1/$2');
$routes->post('getslots/(:segment)', 'Home::getslots/$1');
$routes->post('getslotsss/(:segment)', 'Home::getslotsss/$1');

$routes->post('createemp/(:segment)', 'Home::createemp/$1');

$routes->post('createemp/(:segment)/(:num)', 'Home::createemp/$1/$2');

$routes->post('createemp/(:segment)/(:num)', 'Home::createemp/$1/$2'); 
$routes->post('submitappointment', 'Home::submitappointment'); 
$routes->post('subscribtionappointment', 'Home::subscribtionappointment'); 
$routes->post('fetchslotsforcustome', 'Home::fetchslotsforcustome'); 


$routes->post('get_todays_appointment_data/(:segment)/(:num)', 'Home::get_todays_appointment_data/$1/$2');
$routes->get('get_todays_appointment_data/(:segment)', 'Home::get_todays_appointment_data/$1');
$routes->get('getAppointmentsWithSlots', 'Home::getAppointmentsWithSlots');
$routes->get('get_conducted_appointments', 'Home::getConductedAppointments');
$routes->post('get_consultantwise_appointments', 'Home::get_consultantwise_appointments');
$routes->post('get_upcoming_consultantwise_appointments', 'Home::get_upcoming_consultantwise_appointments');
$routes->post('get_cancelledcounsultant_appointments', 'Home::get_cancelledcounsultant_appointments');

$routes->get('get_upcoming_appointments', 'Home::getUpcomingAppointments');
$routes->get('get_cancelled_appointments', 'Home::getCancelledAppointments');
$routes->get('get_pending_appointments', 'Home::getPendingAppointments');
$routes->post('submitPayment', 'Home::submitPayment');
$routes->get('readAppointments', 'Home::readAppointments');
$routes->post('readAppointments', 'Home::readAppointments');

$routes->post('get_consultantwisepending_appointments', 'Home::get_consultantwisepending_appointments');

$routes->get('getUserDetails/(:segment)', 'Home::getUserDetails/$1');

$routes->post('get_filtered_appointments/(:segment)', 'Home::getFilteredAppointments/$1');
$routes->post('getFilteredAppointmentstodays/(:segment)', 'Home::getFilteredAppointmentstodays/$1');

$routes->post('get_filtered_report/(:segment)', 'Home::getFilteredReport/$1');

$routes->post('patient-details', 'Home::patient_details');
// $routes->get('patient-details', 'Home::patient_details');
$routes->post('submit-prescriptions', 'Home::submit_prescriptions');
$routes->post('submit-tests', 'Home::submit_tests');
$routes->get('get-appointment-price/(:segment)', 'Home::get_appointment_price/$1');
$routes->get('complete-patient-details/(:num)', 'Home::getCompletePatientDetails/$1');
$routes->post('certificate', 'Home::submit_certifate_details');








// $routes->post('getsheduledata/(:segment)/(:num)', 'Home::getsheduledata/$1/$2');

$routes->get('schedule', 'Home::getScheduleData');



$routes->post('slotsmanage', 'Home::slotsmanage');
$routes->get('sendemail', 'Home::sendemail');
