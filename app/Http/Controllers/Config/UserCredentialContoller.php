<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Illuminate\Http\Request;
use App\Models\Config\Users;
use App\Models\Config\UsersCredential;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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

        $rpEntity       = new PublicKeyCredentialRpEntity('KBU', 'localhost');
        $userEntity     = new PublicKeyCredentialUserEntity($userId, random_bytes(16), $userName);

        $challenge = bin2hex(random_bytes(16));
        $challenge = base64_encode($challenge);
        $challenge = rtrim(strtr($challenge, '+/', '-_'), '=');

        Cache::put("challenge_{$user_id}", $challenge, 300);
        Log:info('generated challenge '.$challenge);

        $publicKeyCredentialParametersList = [
            PublicKeyCredentialParameters::create('public-key', -7), // More interesting algorithm
            PublicKeyCredentialParameters::create('public-key', -257),  //      ||
        ];

        $options =
            PublicKeyCredentialCreationOptions::create(
                $rpEntity,
                $userEntity,
                $challenge,
                pubKeyCredParams: $publicKeyCredentialParametersList,
                authenticatorSelection: AuthenticatorSelectionCriteria::create(),
                attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
                excludeCredentials: [],
                timeout: 30000,
            ) ;

        Log::info('generated challenge 2 '.$challenge);

        return response()->success('success',[
            'publicKeyCredentialCreationOptions'=>$options
        ]);
   }

    public function registration_finish(Request $request) {
        $data = $request->json()->all();

        $validator=Validator::make($data,[
            'user_id'=>'bail|required',
            'user_name'=>'bail|required',
            'challenge'=>'bail|required',
        ],[
            'user_id.required'=>'User ID harus diisi',
            'user_name.required'=>'Nama pengguna harus diisi',
            'challenge.required'=>'Challenge harus diisi',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $user_id    = $data['user_id'];
        $user_name  = $data['user_name'];
        $uuid       = $data['uuid'] ?? '';
        $challenge  = $data['challenge'] ?? '';
        $credential = $data['credential'];
        $credentialId = $credential['id'];

        $storedChallenge = Cache::get("challenge_{$user_id}");
        Log::info('storedChallenge :'.$storedChallenge);
        Log::info('challenge :'.$challenge);
        if (!$storedChallenge) {
            return response()->error('', 400, 'Invalid or expired challenge');
        }

        // Remove challenge from cache after use
        Cache::forget("challenge_{$user_id}");

        $publicKey = $credential['response']['publicKey'];
        $user = Users::where('user_id',$user_id)->first();

        $attestationStatementSupportManager = new AttestationStatementSupportManager();
        $attestationStatementSupportManager->add(new PackedAttestationStatementSupport());
        $attestationStatementSupportManager->add(new FidoU2FAttestationStatementSupport());

        $validator = new AuthenticatorAttestationResponseValidator($attestationStatementSupportManager);

        try {
            $publicKeyCredentialLoader = new PublicKeyCredentialLoader();
            $publicKeyCredential = $publicKeyCredentialLoader->load($credential);
            $authenticatorAttestationResponse = $publicKeyCredential->getResponse();

            $validationData = $validator->check(
                $authenticatorAttestationResponse,
                $challenge,
                $request->getHost()
            );
        } catch (\Throwable $e) {
            Log::error('Validation failed: ' . $e->getMessage());
            return response()->error('', 500, 'Credential validation failed');
        }

        $credentialId = base64_encode($credential['id']);
        $publicKey = base64_encode($credential['response']['publicKey']);
        $uuid = $data['uuid'] ?? '';

        $user = Users::where('user_id', $user_id)->first();

        $uc = UsersCredential::where('uuid_rec', $uuid)->first();
        if (!$uc) {
            $uc = new UsersCredential();
            $uc->uuid_rec = Str::uuid();
            $uc->user_sysid = $user->sysid;
            $uc->created_date = now();
            $uc->created_by = auth()->user()->id ?? 'system';
        } else {
            $uc->updated_date = now();
            $uc->updated_by = auth()->user()->id ?? 'system';
        }

        $uc->finger_id = 1;
        $uc->credential_id = $credentialId;
        $uc->public_key = Crypt::encryptString($publicKey);
        $uc->type = $credential['type'] ?? '';
        $uc->sign_count = $credential['signCount'] ?? 0;
        $uc->attestationobject = $credential['response']['attestationObject'] ?? '';
        $uc->authenticatorData = $credential['response']['authenticatorData'] ?? '';
        $uc->clientdatajson = $credential['response']['clientDataJSON'] ?? '';
        $uc->save();

        return response()->success('success', 'Rekam sidik jari berhasil');
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

        $challenge = bin2hex(random_bytes(16));
        $challenge = base64_encode($challenge);
        $challenge = rtrim(strtr($challenge, '+/', '-_'), '=');
        $options = [
            'challenge' =>  $challenge,
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
        $challenge = session('webauthn_login_challenge');
        Log::info($challenge);

        $assertion = $data['assertion'];
        $user_id   = $data['user_id'];

        $user = Users::where('user_id',$user_id)->first();
        if (!$user) {
            return response()->error('',501,'User tidak ditemukan');
        }

        $uc=UsersCredential::where('user_sysid',$user->sysid)->first();

        $validator = new AuthenticatorAssertionResponseValidator();

        $isValid = $validator->validate(
            $assertion['response'],
            $challenge,
            $user->$uc->pluck('public_key')->toArray()
        );

        if ($isValid) {
            return response()->success('success','Login berhasil');
        }
        return response()->error('',501,'Verifikasi gagal!');

    }

}
