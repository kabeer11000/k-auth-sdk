<?php
declare(strict_types=1);
namespace Kabeers;
require 'small-http.php';

use Error;
use Exception;

session_start();
class KAuth
{
    public $tokens = null;
    private $client_secret = null;
    private $client_public = null;
    private $save_dir = null;
    private $auth_uri = null;
    private $endPoints = array(
        'UserInfo' => 'https://kabeers-auth.herokuapp.com/user/userinfo',
        'AccessToken' => 'https://kabeers-auth.herokuapp.com/auth/token',
        'RefreshToken' => 'https://kabeers-auth.herokuapp.com/auth/refresh',
        'AuthURI' => 'https://kabeers-auth.herokuapp.com/auth'
    );
    private $session_state = false;

    public function getUserInfo(String $token)
    {
        if (!$token || !$this->client_secret || !$this->client_public) return 0;
        return preg_replace("/\s+/", "", SmallHttp::HTTPPost($this->endPoints['UserInfo'],
            array(
                "client_public" => "$this->client_public",
                "client_secret" => "$this->client_secret",
                "token" => "$token"
            )
        ));
    }

    public function init(String $client_public, String $client_secret, String $save_dir, Bool $session_state = false)
    {
        if (!$client_public || !$client_secret || !$save_dir) return 0;
        $this->client_public = $client_public;
        $this->client_secret = $client_secret;
        $this->save_dir = $save_dir;
        if (isset($_GET['code'])) {
            if ($session_state === false){
                $token_response = json_decode($this->getAccessTokens(htmlspecialchars($_GET['code'])), true);
                if ($token_response !== null && gettype($token_response) === 'array') {
                    $this->tokens = $token_response;
                    return true;
                }
            }else{
                if (isset($_SESSION['kauth_state']) && $_GET['state']!==$_SESSION['kauth_state']) {return 0;}
                $token_response = json_decode($this->getAccessTokens(htmlspecialchars($_GET['code'])), true);
                if ($token_response !== null && gettype($token_response) === 'array') {
                    $this->tokens = $token_response;
                    return true;
                }
            }
        }
        return true;
    }

    public function refreshToken(String $refresh_token)
    {
        if (!$this->client_secret || !$this->client_public || !$refresh_token) return 0;
        $jwt_payload = json_decode(base64_decode(urldecode(explode('.', $refresh_token)[1])));
        if ($jwt_payload->iat > $jwt_payload->exp) {
            throw new Error('Refresh Token Expired');
        }
        return SmallHttp::HTTPPost($this->endPoints['RefreshToken'],
            array(
                "client_public" => "$this->client_public",
                "client_secret" => "$this->client_secret",
                "refresh_token" => "$refresh_token"
            ));
    }

    private function getAccessTokens(String $code)
    {
        if (!$this->client_secret || !$this->client_public || !$code) return 0;
        return SmallHttp::HTTPPost($this->endPoints['AccessToken'],
            array(
                "client_public" => "$this->client_public",
                "client_secret" => "$this->client_secret",
                "auth_code" => "$code"
            ));
    }

    public function saveToken(String $key, String $value)
    {
        if (!$value || !$key) return 0;
        $return = false;
        $key = md5($key);
        if (isset($this->save_dir) && $this->save_dir !== '' || null) {
            $save_key = fopen("$this->save_dir/$key.kauth_store", "w") or die("Unable to open file!");
            fwrite($save_key, "$value");
            fclose($save_key);
            $return = true;
        }
        return $return;
    }

    public function getToken(String $key)
    {
        if (!$key) return 0;
        $return = null;
        $key = md5($key);
        if (isset($this->save_dir) && $this->save_dir !== '' || null) {
            $save_contents = null;
            try {
                $save_contents = file_get_contents("$this->save_dir/$key.kauth_store");
                $return = true;
            } catch (Exception $e) {
                $return = false;
            }
            return $save_contents !== null || '' ? $save_contents : $return;
        }
        return true;
    }
    
    public function deleteToken( String $key ){
        if (!$key) return 0;
        $return = null;
        $key = md5($key);
        if (isset($this->save_dir) && $this->save_dir !== null) {
            try {
                unlink("$this->save_dir/$key.kauth_store");
                $return = true;
            } catch (Exception $e) {
                $return = false;
            }
            return $return;
        }
        return true;
    }

    public function createAuthURI(Array $claims, String $callback, String $state, String $response_type = 'code', String $prompt = 'consent')
    {
        $callback = urlencode($callback);
        $claims = urlencode(join(array_unique($claims), '|'));
        if (!$state) {
            $state = uniqid();
        }
        if (isset($this->session_state)){$_SESSION['kauth_state'] = $state;}
        $endPoint = $this->endPoints['AuthURI'];
        $this->auth_uri = "$endPoint/$this->client_public/$claims/$response_type/$callback/$state/$prompt";
    }

    public function render(String $height, String $width, String $theme = 'dark')
    {
        if (!$height || !$width || !$theme || !$this->auth_uri || $this->auth_uri === null) {
            return false;
        }
        if ($theme === 'dark') {
            return "<div class='kauth_btn--container'><a href='$this->auth_uri' class='kauth_btn--anchor'><img alt='Login With Kabeers Network' class='kauth_btn--image' src='https://cdn.jsdelivr.net/gh/kabeer11000/kauthsdk-php/dist/dark.svg' style='width:$width;height:$height'></a></div>";
        }
        return "<div class='kauth_btn--container'><a href='$this->auth_uri' class='kauth_btn--anchor'><img alt='Login With Kabeers Network' class='kauth_btn--image' src='https://cdn.jsdelivr.net/gh/kabeer11000/kauthsdk-php/dist/light.svg' style='width:$width;height:$height'></a></div>";
    }

    public function redirect()
    {
        if (!$this->auth_uri || $this->auth_uri === null) {
            return false;
        }
        header("Location:$this->auth_uri");
        return 0;
    }
}
?>
