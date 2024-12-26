<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use Webauthn\WebAuthn;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\RelyingPartyIdentity;
use Webauthn\UserIdentity;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\PublicKeyCredentialSource;
use PDO;
use Illuminate\Http\Request;
use App\Models\Config\Users;
use App\Models\Config\UsersCredential;
use PagesHelp;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class UserCredentialContoller extends Controller
{
   public function registration(Request $request) {
        $validator=Validator::make($request->all(),[
            'user_id'=>'bail|required',
            'user_name'=>'bail|required',
        ],[
            'user_id.required'=>'User ID harus diisi',
            'user_name.required'=>'Nama pengguna harus diisi',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        $user_id=$request->user_id;

        $user = Users::where('user_id',$user_id)->first();
        if (!$user) {
            return response()->error('',501,'User tidak ditemukan');
        }

        $userId         = $user->user_id;
        $userName       = $user->user_name;
        $userDisplayName= $user->user_name;

        $challenge = random_bytes(32);

        // Create WebAuthn registration options
        $rp = new RelyingPartyIdentity('KBU', 'karuniabakti.com');
        $user = new UserIdentity($userId, $userName, $userDisplayName);
        $authenticatorSelection = new AuthenticatorSelectionCriteria();
        $authenticatorSelection->setResidentKey('required');
        $authenticatorSelection->setUserVerification('preferred');
        $credentialParams = [
            new PublicKeyCredentialParameters('public-key', -7), // ES256
        ];

        $options = new PublicKeyCredentialCreationOptions(
            $challenge,
            $rp,
            $user,
            $credentialParams,
            60000, // Timeout
            $authenticatorSelection
        );
        $response=[
            'publicKeyCredentialCreationOptions'=>$options
        ];
        return response()->success('success',$response);
   }

    public function registration_finish(Request $request) {
        $data = $request->json()->all();

        $validator=Validator::make($data,[
            'user_id'=>'bail|required',
            'user_name'=>'bail|required',
        ],[
            'user_id.required'=>'User ID harus diisi',
            'user_name.required'=>'Nama pengguna harus diisi',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $credential = json_decode($data['credential']);
        $user_id    = $data['user_id'];
        $user_name  = $data['user_name'];
        $uuid       = $data['uuid'] ?? '';
        $credentialId = $credential->id;

        $publicKey = $credential->response->attestationObject;
        $user = Users::where('user_id',$user_id)->first();

        $uc=UsersCredential::where('uuid_rec',$uuid)->first();
        if (!$uc) {
            $uc = new UsersCredential();
            $uc->uuid_rec     = Str::uuid();
            $uc->user_sysid   = $user->sysid;
            $uc->created_date = Date('Y-m-d H:i:s');
            $uc->created_by   =PagesHelp::Session()->user_id;
        } else {
            $uc->updated_date = Date('Y-m-d H:i:s');
            $uc->updated_by   =PagesHelp::Session()->user_id;
        }
        $uc->finger_id     =  1;
        $uc->credential    =  $credentialId;
        $uc->public_key    =  $publicKey;
        $uc->sign_count    =  0;
        $uc->save();
        return response()->success('success','Rekam sidik jadi berhasil');
    }

    public function login(Request $request) {
        $validator=Validator::make($request->all(),[
            'user_id'=>'bail|required',
        ],[
            'user_id.required'=>'User ID harus diisi',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        $user_id=$request->user_id;

        $user = Users::where('user_id',$user_id)->first();
        if (!$user) {
            return response()->error('',501,'User tidak ditemukan');
        }

        $uc=UsersCredential::where('user_sysid',$user->sysid)->first();
        // Opsi autentikasi
        $challenge=random_bytes(32);
        $options = [
            'challenge' =>  bin2hex(random_bytes(32)),
            'allowCredentials' => [
                ['id' => $uc->credential_id,
                'type' => 'public-key'
                ]
            ],
            'timeout'=>120000
        ];
        $response=[
            'publicKeyCredentialRequestOptions' => $options
        ];

        return response()->success('success',$response);
    }

    public function login_finish(Request $request) {
        $data = $request->json()->all();

        $validator=Validator::make($data,[
            'user_id'=>'bail|required',
        ],[
            'user_id.required'=>'User ID harus diisi',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        $assertion = json_decode($data['assertion']);
        $user_id   = $data['user_id'];

        $user = Users::where('user_id',$user_id)->first();
        if (!$user) {
            return response()->error('',501,'User tidak ditemukan');
        }

        $uc=UsersCredential::where('user_sysid',$user->sysid)->first();

        $base64 = UserCredentialContoller::base64url_to_base64($uc->public_key);
        $publicKeyPem = UserCredentialContoller::base64_to_pem($base64);
        Log::info($publicKeyPem);
        $publicKey    = openssl_pkey_get_public($publicKeyPem);
        if (!$publicKey) {
            return response()->error('',501,'Public Key gagal diambil '.$uc->public_key);
        } else {
            return response()->error('',501,$uc->public_key);

        }

        // Verifikasi tanda tangan
        $authenticatorData = $assertion->response->authenticatorData;
        $clientDataJSON    = $assertion->response->clientDataJSON;
        $signature         = $assertion->response->signature;

        $isVerified = UserCredentialContoller::verify_signature($authenticatorData, $clientDataJSON, $signature, $publicKey);
        if (!$isVerified) {
            return response()->success('success','Login berhasil');
        } else {
            return response()->error('',501,'Verifikasi gagal!');
        }
    }

    static function verify_signature($authenticatorData, $clientDataJSON, $signature, $publicKey)
    {
        // Persiapkan data yang akan divalidasi
        $clientDataJSON = UserCredentialContoller::base64url_decode($clientDataJSON);
        $authenticatorData = UserCredentialContoller::base64url_decode($authenticatorData);
        $signature = UserCredentialContoller::base64url_decode($signature);

        // Gabungkan clientDataJSON dan authenticatorData untuk membentuk data yang diverifikasi
        $dataToVerify = $authenticatorData . $clientDataJSON;

        // Gunakan fungsi OpenSSL untuk memverifikasi tanda tangan
        $result = openssl_verify($dataToVerify, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    static function base64url_decode($data) {
        // Ganti karakter yang berbeda di base64url encoding menjadi karakter standar base64
        $base64 = strtr($data, '-_', '+/');
        // Padding yang hilang harus ditambahkan
        $base64 = str_pad($base64, strlen($base64) + (4 - strlen($base64) % 4) % 4, '=', STR_PAD_RIGHT);
        return base64_decode($base64);
    }

    static function base64url_to_base64($base64url) {
        // Ubah karakter base64url ke base64 standar
        $base64 = strtr($base64url, '-_', '+/');
        // Tambahkan padding yang hilang
        return str_pad($base64, strlen($base64) + (4 - strlen($base64) % 4) % 4, '=', STR_PAD_RIGHT);
    }

    static function base64_to_pem($base64) {
        $pem = "-----BEGIN PUBLIC KEY-----\n";
        $pem .= chunk_split($base64, 64, "\n");
        $pem .= "-----END PUBLIC KEY-----\n";
        return $pem;
    }

}
