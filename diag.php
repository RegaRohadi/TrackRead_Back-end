<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
echo "USERS=".User::count()."\n";
$u = User::where('email','diag_check@test.com')->first();
if($u){ $u->delete(); echo "deleted old diag\n"; }
try{
  $u = User::create(['name'=>'Diag','email'=>'diag_check@test.com','password'=>'password123']);
  echo "created id=".$u->id." hash=".substr($u->password,0,10)." check=". (Hash::check('password123',$u->password)?'OK':'FAIL')."\n";
  $fresh = User::find($u->id);
  echo "fresh check=". (Hash::check('password123',$fresh->password)?'OK':'FAIL')."\n";
  echo "isHashed=". (Hash::isHashed($fresh->password)?'yes':'no')."\n";
}catch(Exception $e){ echo "ERR create: ".$e->getMessage()."\n"; }
echo "route check: ";
try{ $r=app('router')->getRoutes(); echo "routes=".count($r)."\n"; }catch(Exception $e){ echo $e->getMessage()."\n"; }
