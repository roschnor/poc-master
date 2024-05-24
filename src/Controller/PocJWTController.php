<?php

declare(strict_types=1);

namespace App\Controller;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\Algorithm\None;
use Jose\Component\Signature\Algorithm\PS256;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PocJWTController extends AbstractController
{
    private Request $request;
    private JWK $privateKey;
    private JWK $publicKey;

    public function __construct(RequestStack $request)
    {
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

        $noneHack = $this->jwtgenNone();
        $hs256Hack = $this->jwtgenHS256();

        return $this->json(['noneHack' => $noneHack, 'hs256Hack' => $hs256Hack]);
    }

    #[Route('/jwt-login', name: 'jwtlogin')]
    public function jwtlogin(): Response
    {
        // GET TOKEN FROM HEADER
        $token = $this->tokenFromHeader();
        // VERIFY
        $isVerified = $this->isVerified($token);

        if ($isVerified) {
            return $this->json('YOU ARE LOGGED IN');
        }

        return $this->json('PLEASE LOG IN');
    }

    #[Route('/jwt-gen', name: 'jwtgen')]
    public function jwtgen(): Response
    {
        // The algorithm manager with RS256
        $algorithmManager = new AlgorithmManager([new RS256()]);
        // JWS Builder.
        $jwsBuilder = new JWSBuilder($algorithmManager);
        $payload = json_encode([
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 36000,
            'iss' => 'Test.tld',
            'aud' => 'Test.tld',
            'user' => 'test@test.tld',
        ]);
        // JWS WITH Private Key and RS256
        $jwk = $this->privateKey;
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
        $privateKeyFile = explode('src', __DIR__)[0].'/docker/server/nginx-conf/localhost.key';
        $privateKeyFile = str_replace('/', DIRECTORY_SEPARATOR, $privateKeyFile);
        $privateKey = JWKFactory::createFromKeyFile(
            $privateKeyFile, // The filename
            null,                   // Secret if the key is encrypted, otherwise null
            [
                'use' => 'sig',         // Additional parameters
            ]
        );
        // Public Key as Secret
        $publicKey = JWKFactory::createFromSecret(base64_decode('MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArhNXx6YQs+nGBQUCBVBUaT3GxZd4lBI82hRHKAXQ1FYI1YA2OPrmyYJ0gyDlBrk6jYYP5qhd7Rxtioi0ibTIwpuhKVFhbcHs0FsOZsAjTTRaUjh+sUf/xclugjwPhDneJ4hVO9KTTX5Knw5xanTE72G8fQyqOvv/zM7mrpicg0pBZYQCifkSdRfNFknzuWq4B4Cat6F75CnCVvRPaa7ozZFqMf3g0QUnf/1Id+2njBgoONuAi3ztqdWMSt6vEEtz44aLfEq1qMpISpAEr5CdxwX00cELnAY6bdqHfCcdB/iVoMTLDNHzHmc58m7IIcvRFWPsVgFGVcTvxtHeyWeH0wIDAQAB'));

        return [$publicKey, $privateKey];
    }

    private function tokenFromHeader(): array|string
    {
        $token = explode(' ', $this->request->headers->get('Authorization'));
        if (2 == sizeof($token)) {
            $token = trim($token[1]);
        }

        return $token;
    }

    private function jwtgenNone()
    {
        // The algorithm manager with None
        $algorithmManager = new AlgorithmManager([new None()]);
        // JWS Builder.
        $jwsBuilder = new JWSBuilder($algorithmManager);
        $payload = json_encode([
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 36000,
            'iss' => 'Test.tld',
            'aud' => 'Test.tld',
            'user' => 'test@test.tld',
        ]);
        // NONE Key Type
        $noneKey = JWKFactory::createNoneKey();
        // JWS WITHOUT SIGNATURE
        $jws = $jwsBuilder
            ->create()                               // We want to create a new JWS
            ->withPayload($payload)                  // We set the payload
            ->addSignature($noneKey, ['alg' => 'none']) // We add a signature with a simple protected header
            ->build();                               // We build it
        $serializer = new CompactSerializer(); // The serializer

        // We serialize the signature at index 0 (we only have one signature).
        return $serializer->serialize($jws, 0);
    }

    private function jwtgenHS256()
    {
        $payload = json_encode([
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 36000,
            'iss' => 'Test.tld',
            'aud' => 'Test.tld',
            'user' => 'test@test.tld',
        ]);
        // HS256 Key Type
        $token = 'eyJhbGciOiJIUzI1NiJ9.'.Base64UrlSafe::encodeUnpadded($payload);
        $publicKey = base64_decode('MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArhNXx6YQs+nGBQUCBVBUaT3GxZd4lBI82hRHKAXQ1FYI1YA2OPrmyYJ0gyDlBrk6jYYP5qhd7Rxtioi0ibTIwpuhKVFhbcHs0FsOZsAjTTRaUjh+sUf/xclugjwPhDneJ4hVO9KTTX5Knw5xanTE72G8fQyqOvv/zM7mrpicg0pBZYQCifkSdRfNFknzuWq4B4Cat6F75CnCVvRPaa7ozZFqMf3g0QUnf/1Id+2njBgoONuAi3ztqdWMSt6vEEtz44aLfEq1qMpISpAEr5CdxwX00cELnAY6bdqHfCcdB/iVoMTLDNHzHmc58m7IIcvRFWPsVgFGVcTvxtHeyWeH0wIDAQAB');
        $signature = hash_hmac('sha256', $token, $publicKey, true);
        $token .= '.'.Base64UrlSafe::encodeUnpadded($signature);

        return $token;
    }

    private function isVerified(string $token): bool
    {
        // ALLOWED ALGORITHM
        $algorithmManager = new AlgorithmManager([
            new PS256(),
            new HS256(),
            new RS256(),
            new None(),
        ]);
        // UNSERIALIZE
        $serializerManager = new JWSSerializerManager([new CompactSerializer()]);
        $jws = $serializerManager->unserialize($token);
        // VERIFY
        $jwsVerifier = new JWSVerifier($algorithmManager);

        if ('HS256' != $jws->getSignature(0)->getProtectedHeader()['alg']) {
            // The algorithm HS256 uses the secret key to sign and verify each message. The algorithm RS256 uses the private key to sign the message and uses the public key for authentication.
            // Here we fake it, because the JWSVerifier checks the Key usage and types
            return $jwsVerifier->verifyWithKey($jws, $this->privateKey, 0);
        }

        return $jwsVerifier->verifyWithKey($jws, $this->publicKey, 0);
    }
}