<?php
$staff = \DB::table('staff')->get();
$user = \DB::table('users')->where('name', 'like', '%Waheed%')->get();
echo "STAFF:\n";
print_r($staff->toArray());
echo "USER:\n";
print_r($user->toArray());
