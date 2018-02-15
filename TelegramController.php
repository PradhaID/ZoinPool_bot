<?php

class TelegramController extends \Phalcon\Mvc\Controller
{
    private $ZoinApiUrl = "https://api.telegram.org/bot";
    private $Zoinkey = "BOT_API_KEY";

    public function indexAction()
    {

    }

    public function ZoinPoolAction($key=null){
		$this->view->disable();
		$this->response->setContentType('application/json', 'UTF-8');
		if ($key!=null){
			if ($key!=$this->Zoinkey){
				$response['ok']="false";
				$response['result']="invalid token";
				echo json_encode($response);
			} else {
				$content = file_get_contents("php://input");
				$data = json_decode($content, true);
				$chatID = $data["message"]["chat"]["id"];
				$username = $data["message"]["chat"]["username"];
				$firstName = $data["message"]["chat"]["first_name"];
				$lastName = isset($data["message"]["chat"]["last_name"]) ? $data["message"]["chat"]["last_name"] : '';
	
				//get /start command
				if ($data["message"]["text"]=="/start"){
					$message="Hi *".$firstName.' '.$lastName."* (@".$username.")\n";
					$message.="This is unofficial Zoin Official Bot. I can provide information about your mining statistic on Zoin Official mining Pool *https://zoin.netabuse.net*\n\n";
					$message.="You can give me these commands :\n\n";
					$message.="/set *API_TOKEN* - to set your pool account\n";
					$message.="/s - to give you information about pool and coin statistics\n";
					$message.="/b - to show your balance information\n";
					$message.="/w - to show your workers statistics\n";
					$message.="/p - to show your latest price of Zoin Coin\n";
				
				//get set command
				} else if (substr($data["message"]["text"],0,4)=="/set"){
					if (isset(explode(" ",$data["message"]["text"])[1])){
						$token=explode(" ",$data["message"]["text"])[1];
						if (trim($token)!=""){
							$zoinpool=new Zoinpool();
							$zoinpool->username=$username;
							$zoinpool->api_token=trim($token);
							$zoinpool->save();
							$message="Ok..!Your *API TOKEN* has been saved successfully";
						} else {
							$message="Your *API TOKEN* can't be empty";
						}
					} else {
						$message="Please add your API Token /set *API_TOKEN*";
					}
					
				//get /s command
				} else if ($data["message"]["text"]=="/s"){
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, 'https://zoin.netabuse.net/api/stats');
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
					$content = json_decode(curl_exec($ch),true);

					$message="`Pool Statistics`\n";
					$message.="`----------------`\n";
					$message.="`Blocks    : ".number_format($content['blocks'],0)."`\n";
					$message.="`Diff      : ".number_format($content['difficulty'],8)."`\n";
					$message.="`Net kH/s  : ".$content['networkkhashps']."`\n";
					$message.="`Pool kH/s : ".$content['poolkhashps']."`\n";
					$message.="`Pool Work : ".number_format($content['poolworkers'],0)."`\n";
					
				//get /b command
				} else if ($data["message"]["text"]=="/b"){
					$zoinpool=Zoinpool::findFirst(["username='".$username."'"]);
					if (isset($zoinpool->username)){
						if ($zoinpool->api_token!=""){
							$ch = curl_init();
							curl_setopt($ch, CURLOPT_URL, 'https://zoin.netabuse.net/api/balance?api_token='.$zoinpool->api_token);
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
							curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
							curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
							$content = json_decode(curl_exec($ch),true);

							if (isset($content["error"])){
								$message="Unauthenticated!! Please check your *API TOKEN*. /set *API_TOKEN*";
							} else {
								$message="`Your Pool Balance`\n";
								$message.="`------------------`\n";
								$message.="`Unconfirm : ".$content["unconfirmed"]." ZOI`\n";
								$message.="`Confirmed : ".$content["confirmed"]." ZOI`\n";
								$message.="`Earned    : ".$content["earned_so_far"]." ZOI`\n"; 
							}
						} else {
							$message="Please set your *API TOKEN* first. /set *API_TOKEN*";
						}
					} else {
						$message="Please set your *API TOKEN* first. /set *API_TOKEN*";
					}
					
				//get /w command
				} else if ($data["message"]["text"]=="/w"){
					$zoinpool=Zoinpool::findFirst(["username='".$username."'"]);
					if (isset($zoinpool->username)){
						if ($zoinpool->api_token!=""){
							$ch = curl_init();
							curl_setopt($ch, CURLOPT_URL, 'https://zoin.netabuse.net/api/workers?api_token='.$zoinpool->api_token);
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
							curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
							curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
							$contents= json_decode(curl_exec($ch),true);

							if (isset($content["error"])){
								$message="Unauthenticated!! Please check your *API TOKEN*. /set *API_TOKEN*";
							} else {
								$ch = curl_init();
								curl_setopt($ch, CURLOPT_URL, 'https://zoin.netabuse.net/api/sharestats?api_token='.$zoinpool->api_token);
								curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
								curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
								curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
								$share= json_decode(curl_exec($ch),true);

								$user="";
								foreach($share["usershares"]["workers"] as $key => $value){
									$user=explode(".",$key)[0];
								}

								$totalHs=$totalKHs=0;
								$message="`Active Workers` \n";
								$message.="`--------------`\n";
								foreach($contents as $content){
									if ($content["active"]=="1"){
										$message.="`".$content["username"]." (".(number_format($content["khashs"]/array_sum(array_column($contents, 'khashs'))*100,2))."%) \n > ".$content["hashs"]." H/s ~ ".$content["khashs"]." kH/s`\n";
										$message.="` > Good: ".$share["usershares"]["workers"][$user.".".$content["username"]]["good"].", Bad: ".$share["usershares"]["workers"][$user.".".$content["username"]]["bad"]."`\n";
										$totalHs=$totalHs+$content["hashs"];
										$totalKHs=$totalKHs+$content["khashs"];
									}
								}
								$message.="`---------------------------`\n";
								$message.="`Total\n > ".number_format($totalHs,2)." H/s | ".number_format($totalKHs,2)." kH/s`\n\n";

								$message.="`InActive Workers`\n";
								$message.="`----------------`\n";
								foreach($contents as $content){
									if ($content["active"]=="0"){
										$message.="`".$content["username"]." \n > ".$content["hashs"]." H/s ~ ".$content["khashs"]." kH/s`\n";
									}
								}

								$message.="\n`Shares` \n";
								$message.="`------`\n";
								$message.="`Good Shares : ".$share["usershares"]["good"]." (".$share["usershares"]["goodp"]."%)`\n";
								$message.="`Bad Shares  : ".$share["usershares"]["bad"]." (".$share["usershares"]["badp"]."%)`\n";
								$message.="`Contribution: ".$share["usershares"]["round_percentage"]."%`\n";
								$message.="`Estimated   : ".$share["usershares"]["estimated_earning"]." ZOI`\n\n";
							}
						} else {
							$message="Please set your *API TOKEN* first. /set *API_TOKEN*";
						}

					} else {
							$message="Please set your *API TOKEN* first. /set *API_TOKEN*";
					}
				//get /p command
				} else if ($data["message"]["text"]=="/p"){
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, 'https://min-api.cryptocompare.com/data/price?fsym=ZOI&tsyms=BTC,ETH,USD,EUR,IDR');
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

					$content = json_decode(curl_exec($ch),true);
					$message="`Zoin Coin (Zoi) Price`\n";
					$message.="`----------------------`\n";
					$message.="`BTC : ".number_format($content['BTC'],8)." BTC`\n";
					$message.="`ETH : ".number_format($content['ETH'],8)." ETH`\n";
					$message.="`USD : $".number_format($content['USD'],2)."`\n";
					$message.="`EUR : €".number_format($content['EUR'],2)."`\n";
					$message.="`IDR : Rp".number_format($content['IDR'],2)."`\n";
				} else {
					$message="Oops!! Command not found, please check /start to show list commands";
				}
				if (isset($message)){
					if (isset($data["message"]["message_id"]))
						$this->sendMessage($chatID, $message,  $data["message"]["message_id"]);
					else
						$this->sendMessage($chatID, $message, null);
				}
			}
		} else {
			echo 'Invalid access to ZoinPool Bot Telegram';
			error_log("empty key", 3, "/var/log/php-fpm/www-error.log");

		}
    }

    function sendMessage($chatID, $message, $replyTo){
        $ch = curl_init( $this->ZoinApiUrl.$this->Zoinkey.'/sendMessage' );

        $payload = json_encode( array( "chat_id"=> $chatID,"text"=>$message,"reply_to_message_id"=>$replyTo,"parse_mode"=>"markdown") );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

        curl_exec($ch);
        curl_close($ch);
    }
}

