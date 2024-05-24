<?php

declare(strict_types=1);

namespace App\Controller;

use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\None;
use Jose\Component\Signature\Algorithm\PS256;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;

class PocJWTController extends AbstractController
{
    private Request $request;
    private JWK $privateKey;
    private JWK $publicKey;

    function __construct(RequestStack $request) {
        $this->request = $request->getMainRequest();

        $cert = $this->getCert();
        $this->privateKey = $cert[1];
        $this->publicKey = $cert[0];
    }

    #[Route('/', name: 'pocjwt')]
    public function poc(): Response
    {
       return $this->redirectToRoute('jwtgen');
    }


    #[Route('/jwt-faker', name: 'jwtfaker')]
    public function jwtfaker(): Response
    {
        // GET TOKEN FROM HEADER
        $token = $this->tokenFromHeader();

        $expl = explode('.', $token);
        $noneHack = 'ewogICJhbGciOiAiTk9ORSIsCiAgInR5cCI6ICJKV1QiCn0.'.$expl[1].'.';
        $hs256Hack = '';
        return $this->json(['noneHack'=>$noneHack,'hs256Hack'=>$hs256Hack]);

    }

    #[Route('/jwt-login', name: 'jwtlogin')]
    public function jwtlogin(): Response
    {
        // GET TOKEN FROM HEADER
        $token = $this->tokenFromHeader();
        // ALLOWED ALGORITHM
        $algorithmManager = new AlgorithmManager([
            new PS256(),
            new HS256(),
            new RS256(),
            new None(),
        ]);
        // UNSERIALIZE
        $serializerManager = new JWSSerializerManager([new CompactSerializer(),]);
        $jws = $serializerManager->unserialize($token);
        // VERIFY
        $jwsVerifier = new JWSVerifier($algorithmManager);
        $isVerified = $jwsVerifier->verifyWithKey($jws, $this->privateKey, 0);

        if($isVerified){
            return $this->json('YOU ARE LOGGED IN');
        }
        return $this->json('PLEASE LOG IN');
    }

    #[Route('/jwt-gen', name: 'jwtgen')]
    public function jwtgen(): Response
    {
        // The algorithm manager
        $algorithmManager = new AlgorithmManager([new RS256(),]);
        // RS256 with Private Key
        $jwk = $this->privateKey;
        // JWS Builder.
        $jwsBuilder = new JWSBuilder($algorithmManager);
        $payload = json_encode([
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 36000,
            'iss' => 'Test.tld',
            'aud' => 'Test.tld',
            'user' => 'test@test.tld'
        ]);
        $jws = $jwsBuilder
            ->create()                               // We want to create a new JWS
            ->withPayload($payload)                  // We set the payload
            ->addSignature($jwk, ['alg' => 'RS256']) // We add a signature with a simple protected header
            ->build();                               // We build it
        $serializer = new CompactSerializer(); // The serializer

        $token = $serializer->serialize($jws, 0); // We serialize the signature at index 0 (we only have one signature).

        return $this->json($token);
    }

    private function getCert(): array
    {
        $privateKeyFile = explode('src',__DIR__)[0].'/docker/server/nginx-conf/localhost.key';
        $certFile = explode('src',__DIR__)[0].'/docker/server/nginx-conf/localhost.crt';
        $privateKeyFile = str_replace('/',DIRECTORY_SEPARATOR,$privateKeyFile);
        $certFile = str_replace('/',DIRECTORY_SEPARATOR,$certFile);

        $privateKeyFile = JWKFactory::createFromKeyFile(
            $privateKeyFile, // The filename
            null,                   // Secret if the key is encrypted, otherwise null
            [
                'use' => 'sig',         // Additional parameters
            ]
        );

        $certFile = JWKFactory::createFromCertificateFile(
            $certFile, // The filename
            [
                'use' => 'sig',         // Additional parameters
            ]
        );


        return [$certFile, $privateKeyFile];
    }

    private function tokenFromHeader(): array|string
    {
        $token = explode(' ',$this->request->headers->get('Authorization'));
        if(sizeof($token) == 2) {
            $token = trim($token[1]);
        }
        return $token;
    }

}
