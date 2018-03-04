<?php

namespace App\Jobs;

use App\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Mail\Mailer;

class SendInvitationEmail extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;
    
    protected $sender;
    protected $reciever;
    protected $subject;
    protected $description;
    protected $link;
    
    /**
     * Create a new job instance.
     * @param array $reciever It's reciever information(firstName,lastName,email)
     * @param string $subject It's subject of message
     * @param string $description It's description of message
     * @param string $link It's link to registration
     * @return void
     */
    public function __construct($sender,$reciever,$subject,$link,$description)
    {
        $this->sender       =   $sender;
        $this->reciever     =   $reciever;
        $this->subject      =   $subject;
        $this->description  =   $description;
        $this->link         =   $link;
    }

    /**
     * Execute the job.
     * @param Mailer $mailer It's object to send email
     * @return void
     */
    public function handle(Mailer $mailer)
    {
        $mailer->send('mails.send-invitation-email',array('description'=>$this->description,'link'=>$this->link),function($message){
            $message->from('no-reply@workevolve.com',$this->sender['firstName'].' '.$this->sender['lastName']);
            $message->to($this->reciever['email'],$this->reciever['firstName'].' '.$this->reciever['lastName']);
            $message->subject($this->subject); 
            $message->getSwiftMessage();
        });    
    }
}
