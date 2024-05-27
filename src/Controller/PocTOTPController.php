<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PocTOTPController extends AbstractController
{
    private Request $request;
    private $connection;

    private User $user;

    public function __construct(RequestStack $request, EntityManagerInterface $entityManager)
    {
        $this->request = $request->getMainRequest();
        $this->connection = $entityManager->getConnection();
        $this->user = $entityManager->find(User::class, 1);
    }

    #[Route('/totp', name: 'totp')]
    public function totp(): Response
    {
        $beginn = microtime(true);
        $arr = [];
        for ($i = 0; $i <= 999999; ++$i) {
            $arr[$i] = str_pad($i, 6, 0, STR_PAD_LEFT);
        }
        $dauer = microtime(true) - $beginn;
        dd(sizeof($arr), "Verarbeitung des Skripts: $dauer Sek.");
    }

    #[Route('/totp_gen', name: 'totp_gen')]
    public function totp_gen(): Response
    {
        $otp = $this->gen();
        dd($otp);
    }

    #[Route('/totp1', name: 'totp1')]
    public function totp1(): Response
    {
        $beginn = microtime(true);
        $content = file_get_contents('http://192.168.158.148:8101/totp_login?code=123456');
        $dauer = microtime(true) - $beginn;
        $dauer1 = $dauer * 1000000;
        $dauer2 = $dauer1 / 60;
        $dauer3 = $dauer2 / 60;
        dd(" Verarbeitung des ersten Aufrufs: $dauer Sek.", "Fiktive Verarbeitung des Skripts: $dauer1 Sek.", "Fiktive Verarbeitung des Skripts: $dauer2 Min.", "Fiktive Verarbeitung des Skripts: $dauer3 Std.");
    }

    #[Route('/totp2', name: 'totp2')]
    public function totp2(): Response
    {
        $len0 = 3;
        $otp = $this->gen(1000, 'ich_bin_ein_secret', $len0);
        $max = str_pad(9, $len0, 9, STR_PAD_LEFT);
        // START ATTACK
        $beginn = microtime(true);
        $pos = 0;

        for ($i = 0; $i <= $max; ++$i) {
            $token = str_pad($i, $len0, 0, STR_PAD_LEFT);
            $content = file_get_contents('http://192.168.158.148:8101/totp_login2?code='.$token.'&len='.$len0);
            ++$pos;
            if ('"y"' == $content) {
                break;
            }
        }
        $dauer = microtime(true) - $beginn;
        dd($otp, $pos, $token, "Verarbeitung des Skripts: $dauer Sek.");
    }

    #[Route('/totp3', name: 'totp3')]
    public function totp3(): Response
    {
        $len0 = 5;
        $otp = $this->gen(1000, 'ich_bin_ein_secret', $len0);
        $max = str_pad(9, $len0, 9, STR_PAD_LEFT);
        // START ATTACK
        $beginn = microtime(true);
        $pos = 0;

        for ($i = 0; $i <= $max; ++$i) {
            $token = str_pad($i, $len0, 0, STR_PAD_LEFT);
            $content = file_get_contents('http://192.168.158.148:8101/totp_login3?code='.$token.'&len='.$len0);
            ++$pos;
            if ('"y"' == $content) {
                break;
            }
        }
        $dauer = microtime(true) - $beginn;
        dd($otp, $pos, $token, "Verarbeitung des Skripts: $dauer Sek.");
    }

    #[Route('/totp_login', name: 'totp_login')]
    public function totp_login(): Response
    {
        if ($this->checkCode($this->request->get('code'))) {
            return $this->json('y');
        }

        return $this->json('n');
    }

    #[Route('/totp_login3', name: 'totp_login3')]
    public function totp_login3(): Response
    {
        $max = str_pad(9, $this->request->get('len'), 9, STR_PAD_LEFT);
        // WORST CASE TEST, run CheckCode to simulate calculation time
        $this->checkCode($this->request->get('code'), 'ich_bin_ein_secret', $this->request->get('len'));
        if ($max == $this->request->get('code')) {
            return $this->json('y');
        }

        return $this->json('n');
    }

    #[Route('/totp_login2', name: 'totp_login2')]
    public function totp_login2(): Response
    {
        $max = str_pad(9, $this->request->get('len'), 9, STR_PAD_LEFT);
        $max = intval($max / 2);
        // MIDDLE CASE TEST, run CheckCode to simulate calculation time
        $this->checkCode($this->request->get('code'), 'ich_bin_ein_secret', $this->request->get('len'));
        if ($max == $this->request->get('code')) {
            return $this->json('y');
        }

        return $this->json('n');
    }

    private function checkCode($code, $secret = 'ich_bin_ein_secret', $length = 6): bool
    {
        if ($code == $this->gen(999, $secret, $length) || $code == $this->gen(1000, $secret, $length) || $code == $this->gen(1001, $secret, $length)) {
            return true;
        }

        return false;
    }

    private function gen(int $timeslot = 1000, string $secret = 'ich_bin_ein_secret', int $len = 6): string
    {
        $hash = hash_hmac('sha1', $timeslot, $secret, true);
        $unpacked = unpack('C*', $hash);
        $hmac = array_values($unpacked);
        $offset = ($hmac[count($hmac) - 1] & 0xF);
        $code = ($hmac[$offset] & 0x7F) << 24 | ($hmac[$offset + 1] & 0xFF) << 16 | ($hmac[$offset + 2] & 0xFF) << 8 | ($hmac[$offset + 3] & 0xFF);
        $otp = $code % (10 ** $len);

        return str_pad((string) $otp, $len, '0', STR_PAD_LEFT);
    }

    // MFA TEST
    #[Route('/totp2pass', name: 'totp2pass')]
    public function totp2pass(): Response
    {
        $len0 = 3;
        $len1 = 2;
        $otp = $this->gen(1000, 'ich_bin_ein_secret', $len0);
        $max = str_pad(9, $len0, 9, STR_PAD_LEFT);
        $passmx = str_pad(9, $len1, 9, STR_PAD_LEFT);
        // START ATTACK
        $beginn = microtime(true);
        $pos = 0;

        for ($pass = 0; $pass <= $passmx; ++$pass) {
            for ($i = 0; $i <= $max; ++$i) {
                $token = str_pad($i, $len0, 0, STR_PAD_LEFT);
                $content = file_get_contents('http://192.168.158.148:8101/totp_login2pass?code='.$token.'&len='.$len0.'&pass='.$pass.'&passmx='.$passmx);
                ++$pos;
                if ('"y"' == $content) {
                    break;
                }
            }
        }
        $dauer = microtime(true) - $beginn;
        dd($otp, $pos, $token, "Verarbeitung des Skripts: $dauer Sek.");
    }

    #[Route('/totp_login2pass', name: 'totp_login2pass')]
    public function totp_login2pass(): Response
    {
        $max = str_pad(9, $this->request->get('len'), 9, STR_PAD_LEFT);
        $max = intval($max / 2);
        // MIDDLE CASE TEST, run CheckCode to simulate calculation time
        $this->checkCode($this->request->get('code'), 'ich_bin_ein_secret', $this->request->get('len'));
        if ($max == $this->request->get('code') && $this->request->get('passmx') == $this->request->get('pass')) {
            return $this->json('y');
        }

        return $this->json('n');
    }
}
