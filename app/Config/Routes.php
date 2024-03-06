<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
//$routes->get('/', 'Home::index');
$routes->get('/', 'Login::index');

$routes->get('settings', 'Admin::index');
$routes->post('settings', 'Admin::index');

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

$routes->get('admin', 'Participant::index');
$routes->post('admin', 'Participant::index');
$routes->get('kids', 'Participant::index');
$routes->post('kids', 'Participant::index');
$routes->get('kids/getparent', 'Participant::getparent');
$routes->get('kids/getkids', 'Participant::getkids');
$routes->post('kids/getkids', 'Participant::getkids');
$routes->get('kids/getgroups', 'Participant::getgroups');
$routes->post('kids/getgroups', 'Participant::getgroups');
$routes->get('kids/gethistory', 'Participant::gethistory');
$routes->post('kids/gethistory', 'Participant::gethistory');
$routes->get('kids/pollgroups', 'Participant::pollgroups');
$routes->post('kids/pollgroups', 'Participant::pollgroups');

$routes->get('ipad', 'Registration::index');
$routes->post('ipad', 'Registration::index');
$routes->get('ipad/iframe', 'Registration::iframe');
$routes->post('ipad/iframe', 'Registration::iframe');

$routes->get('staff', 'Staff::index');
$routes->post('staff', 'Staff::index');
$routes->get('staff/getstaff', 'Staff::getstaff');
$routes->post('staff/getstaff', 'Staff::getstaff');

$routes->get('parents', 'Parents::index');
$routes->post('parents', 'Parents::index');
$routes->get('parents/getparent', 'Parents::getparent');
$routes->post('parents/getparent', 'Parents::getparent');

$routes->get('test', 'Test::index');

$routes->get('registration', 'Checkin::registration');
$routes->post('registration', 'Checkin::registration');
$routes->get('login', 'Checkin::login');
$routes->post('login', 'Checkin::login');
