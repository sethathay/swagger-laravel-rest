<?php

namespace App\Jobs;

use App\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Mail\Mailer;

class AcceptInvitationEmail extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;
    
    protected $reciever;
    protected $subject;
    protected $description;
    
    /**
     * Create a new job instance.
     * @param array $reciever It's reciever information(firstName,lastName,email)
     * @param string $subject It's subject of message
     * @param string $description It's description of message
     * @return void
     */
    public function __construct($reciever,$subject,$description)
    {
        $this->reciever     =   $reciever;
        $this->subject      =   $subject;
        $this->description  =   $description;
    }

    /**
     * Execute the job.
     * @param Mailer $mailer It's object to send email
     * @return void
     */
    public function handle(Mailer $mailer)
    {
        $mailer->send('mails.accept-invitation-email',array('description'=>$this->description),function($message){
            $message->from('no-reply@workevolve.com','WorkEvolve');
            $message->to($this->reciever['email'],$this->reciever['firstName'].' '.$this->reciever['lastName']);
            $message->subject($this->subject); 
            $message->getSwiftMessage();
        });
        
    }
}
