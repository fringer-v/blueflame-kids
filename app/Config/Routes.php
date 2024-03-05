<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
//$routes->get('/', 'Home::index');
$routes->get('/', 'Login::index');

$routes->get('admin', 'Admin::index');
$routes->post('admin', 'Admin::index');

$routes->get('calllist', 'Calllist::index');
$routes->post('calllist', 'Calllist::index');
$routes->post('calllist/getcalls', 'Calllist::getcalls');

$routes->get('database', 'Database::index');
$routes->post('database', 'Database::index');

$routes->get('groups', 'Groups::index');
$routes->post('groups', 'Groups::index');
$routes->post('groups/getgrouplist', 'Groups::getgrouplist');
$routes->get('groups/prints', 'Groups::prints');
$routes->post('groups/prints', 'Groups::prints');
$routes->post('groups/pollgroupdata', 'Groups::pollgroupdata');

$routes->get('admin-login', 'Login::index');
$routes->post('admin-login', 'Login::index');

$routes->get('participant', 'Participant::index');
$routes->post('participant', 'Participant::index');
$routes->get('participant/getparent', 'Participant::getparent');
$routes->get('participant/getkids', 'Participant::getkids');
$routes->post('participant/getkids', 'Participant::getkids');
$routes->get('participant/getgroups', 'Participant::getgroups');
$routes->post('participant/getgroups', 'Participant::getgroups');
$routes->get('participant/gethistory', 'Participant::gethistory');
$routes->post('participant/gethistory', 'Participant::gethistory');
$routes->get('participant/pollgroups', 'Participant::pollgroups');
$routes->post('participant/pollgroups', 'Participant::pollgroups');

$routes->get('ipad', 'Registration::index');
$routes->post('ipad', 'Registration::index');
$routes->get('ipad/iframe', 'Registration::iframe');
$routes->post('ipad/iframe', 'Registration::iframe');

$routes->get('registration', 'Checkin::registration');
$routes->post('registration', 'Checkin::registration');
$routes->get('login', 'Checkin::login');
$routes->post('login', 'Checkin::login');

$routes->get('staff', 'Staff::index');
$routes->post('staff', 'Staff::index');
$routes->get('staff/getstaff', 'Staff::getstaff');
$routes->post('staff/getstaff', 'Staff::getstaff');

$routes->get('parents', 'Parents::index');
$routes->post('parents', 'Parents::index');
$routes->get('parents/getparent', 'Parents::getparent');
$routes->post('parents/getparent', 'Parents::getparent');

$routes->get('test', 'Test::index');
