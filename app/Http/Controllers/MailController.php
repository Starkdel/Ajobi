<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;

class MailController extends Controller
{
   public static function send_mail($user_d, $template){
             Mail::send($template, $user_d, function($message)  use  ($user_d){
                 $message->to($user_d['email'], $user_d['name'])->subject($user_d['title']);
                 $message->from($user_d['sender_email'], $user_d['app_name']);
             });

    }
    
    
  
}
