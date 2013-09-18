<?php
/*
 * Created on 07/12/2010
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */

 class abcController extends AppController
 {

	public $name = "abc";
	public $uses = array(
		'Account',	
	);
	public $components = array(
		"Tools",
	);

	public $privilege_levels = array(PRIV_ADMIN);
	
	private $epi_prod_codes = array(
									1  => array(	
												2 	=> "WWN0000001", 
												1	=> "WWN0000002", 
												14	=> "WWN0000003",
												3	=> "WWN0000004", 
												16	=> "WWN0000005",
												15	=> "WWN0000006", 
												13	=> "WWN0000007"
													),
									4 	=> array(	
												2 	=> "WWN0000008", 
												1	=> "WWN0000009", 
												14	=> "WWN0000010",
												3	=> "WWN0000011", 
												16	=> "WWN0000012",
												15	=> "WWN0000013" 
													)
									);								

	private $cashTransactions = array(					//EPI Type, 	Default Description								, +/-
								'DEPO-DR' 			=> array( "VB",	"Failed Deposit - Direct Debit"						, -1),
								'DEPO-CR' 			=> array( "AD",	"Deposit - Direct Debit"							,  1),
								'WDRL-DR' 			=> array( "RW",	"Withdrawal"										, -1),
								'WDRL-CR' 			=> array( "VA",	"Failed Withdrawal"									,  1),
								'FEES-DR' 			=> array( "EX",	""													, -1),
								'FEES-CR' 			=> array( "VA",	""													,  1),
								'INTR-CR' 			=> array( "AD",	"Interest on Cash"									,  1),
								'DVDN-CR' 			=> array( "AD",	"Dividend Payment for %stock_code%"					,  1),
								'CORP-CR' 			=> array( "AD",	"Distribution for %stock_code%"						,  1),
								'WRTE-CR' 			=> array( "AD",	"Write Calls"										,  1),
								'BPAY-CR' 			=> array( "AD",	"Deposit(BPAY)"										,  1),
								'TAXW-DR' 			=> array( "RY",	""													, -1),
								'TAXW-CR' 			=> array( "VA",	""													,  1),
								'SELL-CR' 			=> array( "AD",	"Trade Settlement: Sell %qty% units of %stock_code%",  1),
								'BUY-DR'  			=> array( "RW",	"Trade Settlement: Buy %qty% units of %stock_code%"	, -1),									
								'SELL-DR' 			=> array( "EX",	"Brokerage charge"									, -1)
								);
								
	private $stockTransactions = array(					//EPI Type,+/-, 	Gross Value	, Net Value       ,	Cost Base	    ,Brokerage
								'Buy' 				=> array( "AN",  1, " %ap_qty_pbk% ", " %ap_qty%     ", " %ap_qty_pbk% ", " %bk% "),
								'IntBuy' 			=> array( "AN",  1, " %ap_qty_pbk% ", " %ap_qty%     ", " %ap_qty_pbk% ", " %bk% "),
								'Sell' 				=> array( "RA", -1, " %ap_qty%	   ", " %ap_qty_nbk% ", "0"     	  	, " %bk% "),
								'IntSell' 			=> array( "RA", -1, " %ap_qty%	   ", " %ap_qty_nbk% ", "0"     	  	, " %bk% "),
								'TransferIn' 		=> array( "AT",  1, " %ap_qty%	   ", " %ap_qty% 	 ", " %ap_qty% "    , ""  	  ),
								'TransferOut' 		=> array( "RT", -1, " %ap_qty%	   ", " %ap_qty% 	 ", "0"  		   	, ""   	  ),
								'SwitchIn' 			=> array( "AS",  1, " %ap_qty%	   ", " %ap_qty% 	 ", "0"  		   	, ""   	  ),
								'SwitchOut' 		=> array( "RS", -1, " %ap_qty%	   ", " %ap_qty% 	 ", "0"  		   	, ""   	  ),
								'CorpConsolidation' => array( "RJ", -1, "0"			    , "0"		   	  , "0"  		    , ""   	  ),
								'CorpSplit' 		=> array( "AJ",  1, "0"			    , "0"		   	  , "0"  		    , ""      ),
								);
								/*
	 								%ap_qty% 		= Avg_Price * Quantity
	 								%ap_qty_pbk% 	= Avg_Price * Quantity + Brokerage
	 								%ap_qty_nbk% 	= Avg_Price * Quantity - Brokerage
	 								%bk%			= Brokerage	
	 							*/ 												
	private	$sql_from		= "
							FROM `portfolios` Portfolio
					 		LEFT JOIN `accounts` Account ON Portfolio.account_id = Account.id
					 		LEFT JOIN `clients` Client ON Account.client_id = Client.id
					 		LEFT JOIN `advisors` Advisor ON Client.advisor_id = Advisor.id
					 		LEFT JOIN `dealers` Dealer ON Advisor.dealer_id = Dealer.id
					 		";

	private $sql_cond 		= "
							(Advisor.id = %advisor_id% AND
							(Portfolio.activated IS NOT NULL AND Portfolio.activated <= %requestedDate%) AND
						    (Portfolio.finalized IS NULL OR Portfolio.finalized >= DATE_SUB(%requestedDate%, INTERVAL 7 DAY)))
						    ";						

  	private $ipd_codes = array();
  	
  	private $fileName;
 	private $adv_id;
 	private $Daterequested;
  	
 	function check_type($input_para, $data_type)			//Format CSV fields
	{
		if($data_type == "a"){
			$epi_ppi = ($input_para!="")? '"'. $input_para.'"' : $epi_ppi = "";
			
		}elseif($data_type == "n"){
			$epi_ppi = ($input_para!="")? $epi_ppi =  $input_para : $epi_ppi = "";	
			
		}elseif($data_type == "s"){
			if($input_para!=""){
				$input_para = str_replace("_"," ",$input_para);
				$input_para = str_replace("/"," ",$input_para);
				$input_para = str_replace("\\"," ",$input_para);
				$epi_ppi =  '"'. $input_para.'"';
			}else{
				$epi_ppi = "";
			}	
		}
		return $epi_ppi;
	}
	
	
		
	function dateconvert($date,$func) 						//Format Date
	{
		if ($func == 1) 	//insert conversion
		{ 
			list($day, $month, $year) = split('[/.-]', $date);
			$date = "$year-$month-$day";
			return $date;	// Would echo 2010-12-19 which is the format stored by mysql
		}
		if ($func == 2)		//output conversion
		{ 	
			list($year, $month, $day) = split('[-.]', $date);
			$date = "$day/$month/$year";
			return $date;	// would display 19/12/2010
		}
		if ($func == 3)		//output conversion
		{ 	
			list($year, $month, $day) = split('[-.]', $date);
			$date = "$year$month$day";
			return $date;	// would display 20101219
		}				
	}
		
	function checkData($date)
	{
	    if (!isset($date) || $date=="")
	    {
	        return false;
	    }
	    
	    //match the format of the date
	    if (preg_match ("/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/", $date, $parts))
	  	{
	    	//check weather the date is in valid format
	        if(checkdate($parts[2],$parts[3],$parts[1]))
	          	return true;
	        else
	         	return false;
	  	}
	  	else
	    return false;
	}
		


	
	
	function to_iress($advisor_id, $requestedDate)			//Generates CSV file according to Advisor ID and Date
	{ 		 		
	
		if(!$this->checkData($requestedDate)) return 0;
		
		$no_of_files 			= 1; 	
		$this->requestedDate	= $requestedDate;
		$this->today 			= date("d/m/Y H:m:s");
		$this->record_count		= 0;
		$this->epi_version 		= "4.0";
		$this->sequence_no 		= "1";
		$this->re_sequence		= "F";
 		
 		$data 					= $this -> Advisor -> findById($advisor_id);
 		$this->provider_code	= $data['Dealer']['provider_code'];
 		$this->provider_id		= $data['Dealer']['id'];
 		$this->provider_name 	= $data['Dealer']['name'];
		$this->provider_apir 	= $data['Dealer']['provider_APIR'];
 		$this->adv_code			= $data['Advisor']['code'];
 		
 		if($this->dateconvert(($this->requestedDate),2) == date("d/m/Y")){
			$this->as_at_date = $this->today;	
		}else{
			$this->as_at_date = $this->dateconvert(($this->requestedDate),2)." 23:59:59";	
		} 		
 		
 		if(empty($this->provider_code) || empty($this->adv_code)) return 0;
 			
		$this->sql_cond 	= preg_replace("/\s*%advisor_id%\s*/", "'".$advisor_id."'", $this->sql_cond);
		$this->sql_cond 	= preg_replace("/\s*%requestedDate%\s*/", "'".$requestedDate."'", $this->sql_cond);
				
 		$this->fileName 	= $this->provider_code."_".$this->dateconvert($requestedDate,3).'_EXT' . $this->adv_code. "_".$no_of_files.".csv";
 		$this->file 		= abc_LIST_DIR.DS.$this->fileName;
		$this->fp 			= fopen($this->file, "w+");
 
		$this->iress_hdr();
		$this->iress_ppr();
		$this->iress_ext();
		$this->iress_adv();
		$this->iress_cli();
		$this->iress_pho_add();
		$this->iress_adv_add();
		$this->iress_cli_add();
		$this->iress_pho_pho();
		$this->iress_adv_pho();
		$this->iress_cli_pho();
		$this->iress_pho_ema();
		$this->iress_adv_ema();
		$this->iress_cli_ema();
		$this->iress_acc();
		$this->iress_aco();
		$this->iress_iht_cash();	
		$this->iress_iht_stock();
		$this->iress_ihb_cash();
		$this->iress_ihb_stock();		
		$this->iress_trl();
	

		//	$SQL_DEL 	= "DELETE FROM abc_reporting";
		//	$data_DEL 	= $this -> Account -> query($SQL_DEL);				
		
		fclose($this->fp);
		
		return 1;	
 	}
 	
	function index(){
   	}
   	   	
   	function iress_hdr(){				//Header
   		fputs($this->fp, 
			$this->check_type("HDR","a") . ','.
			$this->check_type($this->epi_version,"a"). ','.
	  		$this->check_type($this->today,"n"). ','. 
	  		$this->check_type("PPR","a"). ','.
	  		$this->check_type($this->sequence_no,"n"). ','.
	  		$this->check_type($this->re_sequence,"a"). 
			"\r\n");
   	}
   	   	
   	function iress_ppr(){				//Platform Provider

		$this->record_count++;				
		fputs($this->fp, 
			$this->check_type("PPR","a") . ','.
			$this->check_type($this->epi_version,"a"). ','.
			$this->check_type($this->provider_code,"a").','.
			$this->check_type($this->provider_name,"a").','.
			$this->check_type($this->provider_apir,"a").','.
			$this->check_type("Unknown","a"). ','.
			$this->check_type("Unknown","a"). 
			"\r\n");
   	}
   	
   	function iress_ext(){				//Extract Identification
		$this->record_count++;		
		fputs($this->fp, 
			$this->check_type("EXT","a") . ','.
			$this->check_type($this->epi_version,"a"). ','.
			$this->check_type($this->provider_code,"a").','.
			$this->check_type(('EXT' .$this->adv_code),"a").
			"\r\n");
   	}
   	   	
   	function iress_adv(){				//Advisor
   		
   		$SQL_ADV = "
			SELECT Contact.firstname, Contact.surname" 
			.$this->sql_from."
            LEFT JOIN direct_equity_live_contacts.contacts Contact ON Advisor.contact_id = Contact.id
            WHERE " .$this->sql_cond." 
            GROUP BY Dealer.provider_code	 		
			";
			
		$data_ADV 		= $this -> Account -> query($SQL_ADV);
		
		foreach($data_ADV as $row)
		{
			$this->record_count++;
					
			fputs($this->fp, 
				$this->check_type("ADV","a") . ','.
				$this->check_type($this->epi_version,"a"). ','.
				$this->check_type($this->provider_code,"a").','.
				$this->check_type(('EXT' .$this->adv_code),"a").','.
				$this->check_type($this->adv_code,"a").','.
				','.
				','.
				$this->check_type($row['Contact']['surname'],"s").','.
				$this->check_type($row['Contact']['firstname'],"s").','.
				','.
				$this->check_type("Unknown","a") . ','.
				','.
				','.
				','.
				','.
				','.
				$this->check_type("Email","a") .						
				"\r\n");			
		}
		$row['Contact']['surname'] 		="";
		$row['Contact']['firstname'] 	="";		
   	}
   	   	
   	function iress_cli(){				//Client
   		$SQL_CLI = "
			SELECT Client.code, Account.code, Account.second_client_id, Account_type.type, 
					Account.company_name, Account.fund_name, Portfolio.id" 
			.$this->sql_from."
			LEFT JOIN `account_types` Account_Type ON Account.account_type_id = Account_Type.id
			WHERE " .$this->sql_cond."
			GROUP BY Account.code, Client.code			
			ORDER BY Account.code, Client.code			
			";
		$data_CLI 		= $this -> Account -> query($SQL_CLI);
						
		$pre_cli_code 					="";
		$pre_cli_code1					="";
		$pre_cli_code2					="";
		$pre_acc_code 					="";
		$pre_cli_val					="";
		
		foreach($data_CLI as $row)
		{
			if(empty($row['Client']['code'])) continue;
			
			if($row['Account_Type']['type']=="INDIVIDUAL" && $pre_cli_code!=$row['Portfolio']['id'])				
			{
				$cli_id 			= $row['Client']['code'];
				$cli_second_id 		= $row['Account']['second_client_id'];
				$pre_cli_code 		= $row['Portfolio']['id'];
		
				$SQL_CLI1 = "SELECT Client1.code, Client1.title, Client1.dob, Contact1.firstname, Contact1.surname, Portfolio1.id
							FROM `portfolios` Portfolio1
							LEFT JOIN `accounts` Account1 ON Portfolio1.account_id = Account1.id
							LEFT JOIN `clients` Client1 ON Account1.client_id = Client1.id
							LEFT JOIN direct_equity_live_contacts.contacts Contact1 ON Client1.contact_id = Contact1.id
							WHERE Client1.code  = $cli_id AND Portfolio1.id = $pre_cli_code
							GROUP BY Client1.code			
				";
				
				$data_CLI1 = $this -> Account -> query($SQL_CLI1);
				
				foreach($data_CLI1 as $row)
				{
					if($pre_cli_code1==$row['Portfolio1']['id'] || $pre_cli_val==$row['Client1']['code']) continue;

					$this->record_count++;
										
					fputs($this->fp, 
						$this->check_type("CLI","a") . ','.
						$this->check_type($this->epi_version,"a"). ','.
						$this->check_type($this->provider_code,"a").','.
						$this->check_type(('EXT' .$this->adv_code),"a").','.
						$this->check_type($this->adv_code,"a").','.
						$this->check_type($row['Client1']['code'],"a").','.
						','.
						','.
						$this->check_type($row['Contact1']['surname'],"s").','.
						$this->check_type($row['Contact1']['firstname'],"s").','.
						$this->check_type($row['Client1']['title'],"a").','.
						$this->check_type("Unknown","a") . ','.
						$this->check_type($this->dateconvert($row['Client1']['dob'],2),"n").','.
						','.
						','.
						','.
						','.
						','.
						$this->check_type("Email","a") .						
						"\r\n");
					
					$pre_cli_val	= $cli_id;				
					$pre_cli_code1	= $row['Portfolio1']['id'];	
					
					if(empty($cli_second_id)) continue;				

					$SQL_CLI2 = "SELECT Client2.code, Client2.title, Client2.dob, Contact2.firstname, Contact2.surname
								FROM `clients` Client2
								LEFT JOIN direct_equity_live_contacts.contacts Contact2 ON Client2.contact_id = Contact2.id
								WHERE Client2.id  = $cli_second_id
								GROUP BY Client2.code			
					";

					$data_CLI2 = $this -> Account -> query($SQL_CLI2);
								
					foreach($data_CLI2 as $row)
					{
						if($pre_cli_code2==$row['Client2']['code']) continue;
														
						$this->record_count++;
																	
						fputs($this->fp, 
							$this->check_type("CLI","a") . ','.
							$this->check_type($this->epi_version,"a"). ','.
							$this->check_type($this->provider_code,"a").','.
							$this->check_type(('EXT' .$this->adv_code),"a").','.
							$this->check_type($this->adv_code,"a").','.
							$this->check_type($row['Client2']['code'],"a").','.
							','.
							','.
							$this->check_type($row['Contact2']['surname'],"s").','.
							$this->check_type($row['Contact2']['firstname'],"s").','.
							$this->check_type($row['Client2']['title'],"a").','.
							$this->check_type("Unknown","a") . ','.
							$this->check_type($this->dateconvert($row['Client2']['dob'],2),"n").','.
							','.
							','.
							','.
							','.
							','.
							$this->check_type("Email","a") .						
							"\r\n");
						
						$pre_cli_code2 		= $row['Client2']['code'];						
					
					}				
				}	
			}
			elseif(!empty($row['Account']['code']) && $pre_acc_code!=$row['Portfolio']['id'] )
			{				
				//Company uses company_name, Fund uses fund_name				
				$org_name = ($row['Account_Type']['type']=="COMPANY" )? $row['Account']['company_name'] : $row['Account']['fund_name'];

				if(empty($org_name)) continue;
				
				$this->record_count++;
				
				fputs($this->fp, 
					$this->check_type("CLI","a") . ','.
					$this->check_type($this->epi_version,"a"). ','.
					$this->check_type($this->provider_code,"a").','.
					$this->check_type(('EXT' .$this->adv_code),"a").','.
					$this->check_type($this->adv_code,"a").','.
					$this->check_type($row['Account']['code'],"a").','.
					','.
					$this->check_type($org_name,"a").','.
					','.
					','.
					','.
					','.
					','.
					','.
					','.
					','.
					','.
					','.
					$this->check_type("Email","a") .						
					"\r\n");
									
				$pre_acc_code			= $row['Portfolio']['id'];								
			};
		$row['Contact']['surname'] 		="";
		$row['Contact']['firstname'] 	="";
		$row['Client']['code']	 		="";	
		$row['Account']['code']	 		="";
		$row['Account']['company_name']	="";				
		$org_name						="";
		}		
   	}
   	 
 	function iress_pho_add(){			// Platform Provider Contact Address
 		$SQL_PHO_ADD = "
			SELECT Address.address1, Address.address2, Address.address3 , Address.suburb,
				Address.state, Address.postcode, Address.country, Address.contact_type_id" 
			.$this->sql_from."
            LEFT JOIN direct_equity_live_contacts.contacts Contact ON Dealer.contact_id = Contact.id
            LEFT JOIN direct_equity_live_contacts.addresses Address ON Contact.id = Address.contact_id
            WHERE " .$this->sql_cond."
			GROUP BY Address.contact_type_id                 		
			";
 		$data_PHO_ADD 	= $this -> Account -> query($SQL_PHO_ADD);
 		
 		foreach($data_PHO_ADD as $row)
		{
			if($row['Address']['contact_type_id'] == 1)
			{
				$pho_address_type = "Street";
				$pho_preferred_address = "T";	
			}elseif($row['Address']['contact_type_id'] == 3)
			{
				$pho_address_type = "Postal";
				$pho_preferred_address = "F";
			}else
			{
				$pho_address_type = "Other";
				$pho_preferred_address = "F";
			} 
			
			if(empty($row['Address']['address1']) && empty($row['Address']['suburb']) && empty($row['Address']['state']) &&
				empty($row['Address']['postcode'])) continue;			
		
			$this->record_count++;
			
			fputs($this->fp, 
				$this->check_type("ADD","a") . ','.
				$this->check_type($this->epi_version,"a"). ','.
				$this->check_type($this->provider_code,"a").','.
				','.
				','.
				','.
				$this->check_type("PHO","a") . ','.
				$this->check_type($pho_address_type,"a") . ','.
				$this->check_type($row['Address']['address1'],"a").','.
				$this->check_type($row['Address']['address2'],"a").','.
				$this->check_type($row['Address']['address3'],"a").','.
				$this->check_type($row['Address']['suburb'],"a").','.
				$this->check_type($row['Address']['state'],"a").','.
				$this->check_type($row['Address']['postcode'],"a").','.
				$this->check_type($row['Address']['country'],"a").','.
				$this->check_type($pho_preferred_address,"a") .	
				"\r\n");		
		}
		$row['Address']['address1'] 		= "";
		$row['Address']['address2'] 		= "";
		$row['Address']['address3'] 		= ""; 
		$row['Address']['suburb'] 			= "";
		$row['Address']['state'] 			= "";
		$row['Address']['postcode'] 		= "";
		$row['Address']['country'] 			= "";
		$row['Address']['contact_type_id']	= ""; 		
 	}
 	    
    function iress_adv_add(){			//Advisor Contact Address
		
		$SQL_ADV_ADD = "
			SELECT Address.address1, Address.address2, Address.address3 , Address.suburb,
				Address.state, Address.postcode, Address.country, Address.contact_type_id" 
			.$this->sql_from."
	        LEFT JOIN direct_equity_live_contacts.contacts Contact ON Advisor.contact_id = Contact.id
	        LEFT JOIN direct_equity_live_contacts.addresses Address ON Contact.id = Address.contact_id
	        WHERE " .$this->sql_cond."
			GROUP BY Address.contact_type_id
		";
		
		$data_ADV_ADD 	= $this -> Account -> query($SQL_ADV_ADD);
				
		foreach($data_ADV_ADD as $row)
		{
			if($row['Address']['contact_type_id'] == 1)
			{
				$adv_address_type = "Street";
				$adv_preferred_address = "T";	
			}elseif($row['Address']['contact_type_id'] == 3)
			{
				$adv_address_type = "Postal";
				$adv_preferred_address = "F";
			}else
			{
				$adv_address_type = "Other";
				$adv_preferred_address = "F";
			} 
			
			if(empty($row['Address']['address1']) && empty($row['Address']['suburb']) && empty($row['Address']['state']) && 
			   empty($row['Address']['postcode'])) continue;
						
			$this->record_count++;
			
			fputs($this->fp, 
				$this->check_type("ADD","a") . ','.
				$this->check_type($this->epi_version,"a"). ','.
				$this->check_type($this->provider_code,"a").','.
				$this->check_type(('EXT' .$this->adv_code),"a"). ','.
				$this->check_type($this->adv_code,"a").','.
				','.
				$this->check_type("ADV","a") . ','.
				$this->check_type($adv_address_type,"a") . ','.
				$this->check_type($row['Address']['address1'],"a").','.
				$this->check_type($row['Address']['address2'],"a").','.
				$this->check_type($row['Address']['address3'],"a").','.
				$this->check_type($row['Address']['suburb'],"a").','.
				$this->check_type($row['Address']['state'],"a").','.
				$this->check_type($row['Address']['postcode'],"a").','.
				$this->check_type($row['Address']['country'],"a").','.
				$this->check_type($adv_preferred_address,"a") .	
				"\r\n");							
		}
		
		$row['Advisor']['code']				= "";
		$row['Address']['address1'] 		= "";
		$row['Address']['address2'] 		= "";
		$row['Address']['address3'] 		= ""; 
		$row['Address']['suburb'] 			= "";
		$row['Address']['state'] 			= "";
		$row['Address']['postcode'] 		= "";
		$row['Address']['country'] 			= "";
		$row['Address']['contact_type_id']	= "";		
    }
       	
   	function iress_cli_add(){			// Client Contact Address
   		
   		$SQL_CLI_ADD = "
			SELECT Client.code, Address.address1, Address.address2, Address.address3 , Address.suburb, Address.state, Address.postcode,
					Address.country, Address.contact_type_id, Account.code,	Account.client_id, Account.second_client_id, Account_type.type,
					Portfolio.id" 
			.$this->sql_from."
			LEFT JOIN `account_types` Account_Type ON Account.account_type_id = Account_Type.id
        	LEFT JOIN direct_equity_live_contacts.contacts Contact ON Client.contact_id = Contact.id
        	LEFT JOIN direct_equity_live_contacts.addresses Address ON Contact.id = Address.contact_id
            WHERE " .$this->sql_cond."
			GROUP BY Client.code  , Account.code, Address.contact_type_id  		 
		";
		
		$data_CLI_ADD 	= $this -> Account -> query($SQL_CLI_ADD);		
		
		$pre_add_cli_code 					= "";
		$pre_add_cli_code2					= "";
		$pre_add_acc_code 					= "";
		
		foreach($data_CLI_ADD as $row)
		{
			if(empty($row['Client']['code']) || empty($row['Address']['address1']) || empty($row['Address']['suburb']) || 
				empty($row['Address']['state']) ||	empty($row['Address']['postcode'])) continue;
		
			$cli_add_count=0;
			if($row['Account_Type']['type']=="INDIVIDUAL" && $pre_add_cli_code!=$row['Client']['code'].$row['Address']['contact_type_id'])
			{				
				if($row['Address']['contact_type_id'] == 1)
				{
					$address_type = "Street";
					$cli_preferred_address = "T";	
				}elseif($row['Address']['contact_type_id'] == 3)
				{
					$address_type = "Postal";
					$cli_preferred_address = "F";
				}else
				{
					$address_type = "Other";
					$cli_preferred_address = "F";
				} 

				$this->record_count++;
				
				fputs($this->fp, 
					$this->check_type("ADD","a") . ','.
					$this->check_type($this->epi_version,"a"). ','.
					$this->check_type($this->provider_code,"a").','.
					$this->check_type(('EXT' .$this->adv_code),"a"). ','.
					$this->check_type($this->adv_code,"a").','.
					$this->check_type($row['Client']['code'],"a").','.
					$this->check_type("CLI","a") . ','.
					$this->check_type($address_type,"a") . ','.
					$this->check_type($row['Address']['address1'],"a").','.
					$this->check_type($row['Address']['address2'],"a").','.
					$this->check_type($row['Address']['address3'],"a").','.
					$this->check_type($row['Address']['suburb'],"a").','.
					$this->check_type($row['Address']['state'],"a").','.
					$this->check_type($row['Address']['postcode'],"a").','.
					$this->check_type($row['Address']['country'],"a").','.
					$this->check_type($cli_preferred_address,"a") .
					"\r\n");
												
				$pre_add_cli_code				= $row['Client']['code'].$row['Address']['contact_type_id'];	
				$cli_add_second_cli 			= $row['Account']['second_client_id'];
					
				if(empty($cli_add_second_cli) || $pre_add_cli_code2==$row['Client']['code'] || $cli_add_count >= 1) continue;

				$pre_add_cli_code2 				= $row['Client']['code'];
				
				$SQL_CLI_ADD2 = "
					SELECT Client.code, Address.address1, Address.address2, Address.address3 , 
						Address.suburb, Address.state, Address.postcode, Address.country, Address.contact_type_id 
					FROM `clients` Client
                	LEFT JOIN direct_equity_live_contacts.contacts Contact ON Client.contact_id = Contact.id
                	LEFT JOIN direct_equity_live_contacts.addresses Address ON Contact.id = Address.contact_id
	                WHERE Client.id  = $cli_add_second_cli 
	                GROUP BY Client.code, Address.contact_type_id		 
				";

				$data_CLI_ADD2 = $this -> Account -> query($SQL_CLI_ADD2);
							
				foreach($data_CLI_ADD2 as $row)
				{
					if($row['Address']['contact_type_id'] == 1)
					{
						$address_type = "Street";
						$cli_preferred_address = "T";	
					}elseif($row['Address']['contact_type_id'] == 3)
					{
						$address_type = "Postal";
						$cli_preferred_address = "F";
					}else
					{
						$address_type = "Other";
						$cli_preferred_address = "F";
					} 
					
					if(empty($row['Client']['code']) || empty($row['Address']['address1']) || empty($row['Address']['suburb']) || 
						empty($row['Address']['state']) ||	empty($row['Address']['postcode'])) continue;
														
					$this->record_count++;
					
					fputs($this->fp, 
						$this->check_type("ADD","a") . ','.
						$this->check_type($this->epi_version,"a"). ','.
						$this->check_type($this->provider_code,"a").','.
						$this->check_type(('EXT' .$this->adv_code),"a"). ','.
						$this->check_type($this->adv_code,"a").','.
						$this->check_type($row['Client']['code'],"a").','.
						$this->check_type("CLI","a") . ','.
						$this->check_type($address_type,"a") . ','.
						$this->check_type($row['Address']['address1'],"a").','.
						$this->check_type($row['Address']['address2'],"a").','.
						$this->check_type($row['Address']['address3'],"a").','.
						$this->check_type($row['Address']['suburb'],"a").','.
						$this->check_type($row['Address']['state'],"a").','.
						$this->check_type($row['Address']['postcode'],"a").','.
						$this->check_type($row['Address']['country'],"a").','.
						$this->check_type($cli_preferred_address,"a") .	
					"\r\n");						
				}								
				$cli_add_count++;
			}else
			{
				if($row['Address']['contact_type_id'] == 1)
				{
					$address_type = "Street";
					$cli_preferred_address = "T";	
				}elseif($row['Address']['contact_type_id'] == 3)
				{
					$address_type = "Postal";
					$cli_preferred_address = "F";
				}else
				{
					$address_type = "Other";
					$cli_preferred_address = "F";
				}
	
				$this->record_count++;
			
				fputs($this->fp, 
					$this->check_type("ADD","a") . ','.
					$this->check_type($this->epi_version,"a"). ','.
					$this->check_type($this->provider_code,"a").','.
					$this->check_type(('EXT' .$this->adv_code),"a"). ','.
					$this->check_type($this->adv_code,"a").','.
					$this->check_type($row['Account']['code'],"a").','.
					$this->check_type("CLI","a") . ','.
					$this->check_type($address_type,"a") . ','.
					$this->check_type($row['Address']['address1'],"a").','.
					$this->check_type($row['Address']['address2'],"a").','.
					$this->check_type($row['Address']['address3'],"a").','.
					$this->check_type($row['Address']['suburb'],"a").','.
					$this->check_type($row['Address']['state'],"a").','.
					$this->check_type($row['Address']['postcode'],"a").','.
					$this->check_type($row['Address']['country'],"a").','.
					$this->check_type($cli_preferred_address,"a") .	
				"\r\n");
			}			
		}
		$row['Client']['code']				= "";
		$row['Account']['code']				= "";
		$row['Advisor']['code']				= "";
		$row['Address']['address1'] 		= "";
		$row['Address']['address2'] 		= "";
		$row['Address']['address3'] 		= ""; 
		$row['Address']['suburb'] 			= "";
		$row['Address']['state'] 			= "";
		$row['Address']['postcode'] 		= "";
		$row['Address']['country'] 			= "";
		$row['Address']['contact_type_id']	= "";		
   	}
   	
	function iress_pho_pho(){			//PHO (Dealer) Contact Phone
		$SQL_PHO_PHO = "
			SELECT Phone.number, Phone.contact_type_id" 
			.$this->sql_from."
            LEFT JOIN direct_equity_live_contacts.contacts Contact ON Dealer.contact_id = Contact.id
            LEFT JOIN direct_equity_live_contacts.phones Phone ON Contact.id = Phone.contact_id
            WHERE  " .$this->sql_cond."
			GROUP BY Phone.contact_type_id  
		";	
		
		$data_PHO_PHO 	= $this -> Account -> query($SQL_PHO_PHO);
						
		$pho_preferred_no 					= "";
		$prev_pho_code 						= "";
				
		foreach($data_PHO_PHO as $row)
		{			
			if(empty($row['Phone']['number'])) continue;
			
			switch($row['Phone']['contact_type_id'])
			{			
				case 5:
					$pho_no_type = "Mobile";						
					$pho_preferred_no = (!$pho_preferred_no)? "T" : "F";											
					break;
					
				case 1:
					$pho_no_type = "Home";						
					$pho_preferred_no = (!$pho_preferred_no)? "T" : "F";						
					break;
					
				case 2:
					$pho_no_type = "Business";						
					$pho_preferred_no = (!$pho_preferred_no)? "T" : "F";
					break;

				case 6:
					$pho_no_type = "Fax";
					$pho_preferred_no = (!$pho_preferred_no)? "T" : "F";
					break;
					
				default:
					$pho_no_type = "Other";
					$pho_preferred_no = (!$pho_preferred_no)? "T" : "F";						 	 	
			}
			
		$this->record_count++;

		fputs($this->fp, 
			$this->check_type("PHO","a") . ','.
			$this->check_type($this->epi_version,"a"). ','.
			$this->check_type($this->provider_code,"a").','.
			','.
			','.
			','.
			$this->check_type("PHO","a") . ','.
			$this->check_type($pho_no_type,"a") . ','.
			','.
			','.
			$this->check_type($row['Phone']['number'],"a").','.
			$this->check_type($pho_preferred_no,"a") .	
			"\r\n");			
		}		
		$row['Phone']['number'] = "";
		$pho_no_type 			= "";
		$pho_preferred_no 		= "";
	}
		
	function iress_adv_pho(){			//Advisor Contact Phone
		$SQL_ADV_PHO = "
			SELECT Phone.number, Phone.contact_type_id" 
			.$this->sql_from."
            LEFT JOIN direct_equity_live_contacts.contacts Contact ON Advisor.contact_id = Contact.id
            LEFT JOIN direct_equity_live_contacts.phones Phone ON Contact.id = Phone.contact_id
            WHERE  " .$this->sql_cond."
			GROUP BY Phone.contact_type_id 
		";
		$data_ADV_PHO 	= $this -> Account -> query($SQL_ADV_PHO);
		
		$adv_preferred_no 		="";
		
		foreach($data_ADV_PHO as $row)
		{
			if(empty($row['Phone']['number'])) continue;
					
			switch($row['Phone']['contact_type_id'])
			{				
				case 5:
					$adv_no_type = "Mobile";
					$adv_preferred_no = (!$adv_preferred_no)? "T" : "F";					
					break;					
				
				case 1:
					$adv_no_type = "Home";
					
					$adv_preferred_no = (!$adv_preferred_no)? "T" : "F";					
					break;
					
				case 2:
					$adv_no_type = "Business";
					$adv_preferred_no = (!$adv_preferred_no)? "T" : "F";											
					break;					

				case 6:
					$adv_no_type = "Fax";
					
					$adv_preferred_no = (!$adv_preferred_no)? "T" : "F";											
					break;
					
				default:
					$adv_no_type = "Other";
					$adv_preferred_no = (!$adv_preferred_no)? "T" : "F";						 	 	
			}
		
			$this->record_count++;
			
			fputs($this->fp, 
				$this->check_type("PHO","a") . ','.
				$this->check_type($this->epi_version,"a"). ','.
				$this->check_type($this->provider_code,"a").','.
				$this->check_type(('EXT' .$this->adv_code),"a").','.
				$this->check_type($this->adv_code,"a").','.
				','.
				$this->check_type("ADV","a") . ','.
				$this->check_type($adv_no_type,"a") . ','.
				','.
				','.
				$this->check_type($row['Phone']['number'],"a").','.
				$this->check_type($adv_preferred_no,"a") .	
				"\r\n");			
		}
		$row['Phone']['number'] 	= "";
		$adv_no_type 				= "";
		$adv_preferred_no 			= "";		
	}
	
	function iress_cli_pho(){			//Client Contact Phone
				
		$SQL_CLI_PHO = "
			SELECT Client.code, Phone.number, Phone.contact_type_id, Account.code, 
					Account_type.type, Account.client_id, Account.second_client_id" 
			.$this->sql_from."
			LEFT JOIN `account_types` Account_Type ON Account.account_type_id = Account_Type.id
        	LEFT JOIN direct_equity_live_contacts.contacts Contact ON Client.contact_id = Contact.id
        	LEFT JOIN direct_equity_live_contacts.phones Phone ON Contact.id = Phone.contact_id
            WHERE  " .$this->sql_cond."
			Group by Client.code , Account.code, Account_type.type,  Phone.contact_type_id  	
		";	
		
		$data_CLI_PHO 	= $this -> Account -> query($SQL_CLI_PHO);		
		
		$cli_preferred_no 			= "";
		$prev_cli_code				= "";
		$prev_acc_code				= "";
		$cli_no_type				= "";
		$second_cli_no_type			= "";
		$second_cli_preferred_no	= "";	
		$prev_cli_code_type			= "";
		$prev_cli_acc_type			= "";	
		$cli_preferred_no_non_ind	= "";
		$prev_cli_code_non_ind		= "";					
		$pho_count 					= 0;				

		foreach($data_CLI_PHO as $row)
		{ 
			if(empty($row['Client']['code']) ||	empty($row['Phone']['number']) || 
			$prev_cli_code_type == $row['Client']['code'].$row['Phone']['contact_type_id']) continue;
				
			if($row['Account_Type']['type']=="INDIVIDUAL")
			{
				if($prev_cli_code != $row['Client']['code'] )
				{
					$cli_preferred_no 	= "";
					$pho_count 			= 0;
				};	
				
				switch($row['Phone']['contact_type_id'])
				{
					case 5:
						$cli_no_type = "Mobile";						
						$cli_preferred_no = (!$cli_preferred_no)? "T" : "F";								
						break;
						
					case 1:
						$cli_no_type = "Home";
						$cli_preferred_no = (!$cli_preferred_no)? "T" : "F";			
						break;
						
					case 2:
						$cli_no_type = "Business";
						$cli_preferred_no = (!$cli_preferred_no)? "T" : "F";							
						break;

					case 6:
						$cli_no_type = "Fax";
						$cli_preferred_no = (!$cli_preferred_no)? "T" : "F";								
						break;
						
					default:
						$cli_no_type = "Other";
						$cli_preferred_no = (!$cli_preferred_no)? "T" : "F";							 
				}
																						
				$this->record_count++;						

				fputs($this->fp, 
					$this->check_type("PHO","a") . ','.
					$this->check_type($this->epi_version,"a"). ','.
					$this->check_type($this->provider_code,"a").','.
					$this->check_type(('EXT' .$this->adv_code),"a").','.
					$this->check_type($this->adv_code,"a").','.
					$this->check_type($row['Client']['code'],"a").','.
					$this->check_type("CLI","a") . ','.
					$this->check_type($cli_no_type,"a") . ','.
					','.
					','.
					$this->check_type($row['Phone']['number'],"a").','.
					$this->check_type($cli_preferred_no,"a") .	
					"\r\n");
				
				$cli_pho_second_cli 	= $row['Account']['second_client_id'];
				//$prev_cli_code_non_ind= $row['Client']['code'];
				$prev_cli_code 			= $row['Client']['code'];						
		
				if($cli_pho_second_cli!="" && $pho_count < 1 )
				{
					$pho_count++;
					
					$SQL_CLI_PHO2 = "
						SELECT Client.code, Phone.number, Phone.contact_type_id
						FROM `clients` Client
						LEFT JOIN direct_equity_live_contacts.contacts Contact ON Client.contact_id = Contact.id
        				LEFT JOIN direct_equity_live_contacts.phones Phone ON Contact.id = Phone.contact_id	
						WHERE Client.id = $cli_pho_second_cli  AND Phone.number!=''
                	";
								
					$data_CLI_PHO2 = $this -> Account -> query($SQL_CLI_PHO2);							
 	 	
					$second_cli_no_type			= "";
					$second_cli_preferred_no	= "";
								
					if(empty($row['Phone']['number']) || empty($row['Client']['code'])) continue;
				
					foreach($data_CLI_PHO2 as $row)
					{						
						switch($row['Phone']['contact_type_id'])
						{						
							case 5:
								$second_cli_no_type = "Mobile";
								$second_cli_preferred_no = (!$second_cli_preferred_no)? "T" : "F";											
								break;
							
							case 1:
								$second_cli_no_type = "Home";						
								$second_cli_preferred_no = (!$second_cli_preferred_no)? "T" : "F";			
								break;
							case 2:
								$second_cli_no_type = "Business";
								$second_cli_preferred_no = (!$second_cli_preferred_no)? "T" : "F";									
								break;
	
							case 6:
								$second_cli_no_type = "Fax";
								$second_cli_preferred_no = (!$second_cli_preferred_no)? "T" : "F";										
								break;
								
							default:
								$second_cli_no_type = "Other";
								$second_cli_preferred_no = (!$second_cli_preferred_no)? "T" : "F";	 	
						}						
						
						if($prev_cli_code == $row['Client']['code']) continue;					
						
						$this->record_count++;
												
						fputs($this->fp, 
							$this->check_type("PHO","a") . ','.
							$this->check_type($this->epi_version,"a"). ','.
							$this->check_type($this->provider_code,"a").','.
							$this->check_type(('EXT' .$this->adv_code),"a").','.
							$this->check_type($this->adv_code,"a").','.
							$this->check_type($row['Client']['code'],"a").','.
							$this->check_type("CLI","a") . ','.
							$this->check_type($second_cli_no_type,"a") . ','.
							','.
							','.
							$this->check_type($row['Phone']['number'],"a").','.
							$this->check_type($second_cli_preferred_no,"a") .	
							"\r\n");
						$cli_second_pho = "";						
					}
				}
			}else //if($prev_cli_acc_type != $row['Client']['code'].$row['Phone']['contact_type_id'])
			{			
				//$cli_preferred_no_non_ind = ($prev_acc_code == $row['Account']['code'])? "" : "";
				
				if($prev_acc_code != $row['Account']['code'] )
				{
					$cli_preferred_no_non_ind 	= "";						
				};					
			
				switch($row['Phone']['contact_type_id']){
					case 5:
						$cli_no_type_non_ind = "Mobile";
						$cli_preferred_no_non_ind = (!$cli_preferred_no_non_ind)? "T" : "F";								
						break;
						
					case 1:
						$cli_no_type_non_ind = "Home";
						$cli_preferred_no_non_ind = (!$cli_preferred_no_non_ind)? "T" : "F";			
						break;
						
					case 2:
						$cli_no_type_non_ind = "Business";
						$cli_preferred_no_non_ind = (!$cli_preferred_no_non_ind)? "T" : "F";								
						break;

					case 6:
						$cli_no_type_non_ind = "Fax";
						$cli_preferred_no_non_ind = (!$cli_preferred_no_non_ind)? "T" : "F";								
						break;
						
					default:
						$cli_no_type_non_ind = "Other";
						$cli_preferred_no_non_ind = (!$cli_preferred_no_non_ind)? "T" : "F";							 
				}

				$this->record_count++;
				
				fputs($this->fp, 
					$this->check_type("PHO","a") . ','.
					$this->check_type($this->epi_version,"a"). ','.
					$this->check_type($this->provider_code,"a").','.
					$this->check_type(('EXT' .$this->adv_code),"a").','.
					$this->check_type($this->adv_code,"a").','.
					$this->check_type($row['Account']['code'],"a").','.
					$this->check_type("CLI","a") . ','.
					$this->check_type($cli_no_type_non_ind,"a") . ','.
					','.
					','.
					$this->check_type($row['Phone']['number'],"a").','.
					$this->check_type($cli_preferred_no_non_ind,"a") .	
					"\r\n");
				$prev_cli_code_non_ind 	= $row['Client']['code'];
				$prev_acc_code 			= $row['Account']['code'];
			}
		 
			//$prev_cli_code 			= $row['Client']['code'];
			//$prev_acc_code 			= $row['Client']['code'];
			$prev_cli_code_type			= $row['Client']['code'].$row['Phone']['contact_type_id'];	
			$prev_cli_acc_type 			= $row['Client']['code'].$row['Phone']['contact_type_id'];
		}
		
		$row['Client']['code']		= "";
		$row['Account']['code'] 	= "";
		$row['Phone']['number'] 	= "";
		$row['Account_Type']['type']= "";
		$cli_no_type 				= "";
		//$cli_preferred_no 			= "";
		$cli_preferred_no_non_ind	= "";
		$cli_no_type_non_ind		= "";		
	}
		
	function iress_pho_ema(){			//PHO(Dealer) Email
		
		$SQL_PHO_EMA = "
			SELECT Email.address, Email.contact_type_id" 
			.$this->sql_from."
            LEFT JOIN direct_equity_live_contacts.contacts Contact ON Dealer.contact_id = Contact.id
            LEFT JOIN direct_equity_live_contacts.emails Email ON Contact.id = Email.contact_id
            WHERE  " .$this->sql_cond."
			GROUP BY Email.contact_type_id 
		";
		
		$data_PHO_EMA 	= $this -> Account -> query($SQL_PHO_EMA);		
		
		foreach($data_PHO_EMA as $row)
		{
			if(empty($row['Email']['address'])) continue;
							
			$this->record_count++;
			
			fputs($this->fp, 
				$this->check_type("EMA","a") . ','.
				$this->check_type($this->epi_version,"a"). ','.
				$this->check_type($this->provider_code,"a").','.
				','.
				','.
				','.
				$this->check_type("PHO","a") . ','.
				$this->check_type("Personal","a") . ','.
				$this->check_type($row['Email']['address'],"a").','.
				$this->check_type("T","a") .	
				"\r\n");			
		}
		$row['Email']['address'] 	= "";
	}
		
	function iress_adv_ema(){			//Advisor Email
		$SQL_ADV_EMA = "
			SELECT Email.address, Email.contact_type_id" 
			.$this->sql_from."
            LEFT JOIN direct_equity_live_contacts.contacts Contact ON Advisor.contact_id = Contact.id
            LEFT JOIN direct_equity_live_contacts.emails Email ON Contact.id = Email.contact_id
            WHERE  " .$this->sql_cond."
			GROUP BY Email.contact_type_id
		";
		
		$data_ADV_EMA 	= $this -> Account -> query($SQL_ADV_EMA);
		
		foreach($data_ADV_EMA as $row)
		{						
			if(empty($row['Email']['address'])) continue;
							
			$this->record_count++;

			fputs($this->fp, 
				$this->check_type("EMA","a") . ','.
				$this->check_type($this->epi_version,"a"). ','.
				$this->check_type($this->provider_code,"a").','.
				$this->check_type(('EXT' .$this->adv_code),"a"). ','.
				$this->check_type($this->adv_code,"a"). ','.
				','.
				$this->check_type("ADV","a") . ','.
				$this->check_type("Personal","a") . ','.
				$this->check_type($row['Email']['address'],"a").','.
				$this->check_type("T","a") .	
				"\r\n");		
		}	
		$row['Email']['address'] 	= "";		
	}
	
	function iress_cli_ema(){			//Client Email
		$SQL_CLI_EMA = "
			SELECT Client.code, Email.address, Email.contact_type_id, Account.code, 
					Account_type.type, Account.second_client_id" 
			.$this->sql_from."
			LEFT JOIN `account_types` Account_Type ON Account.account_type_id = Account_Type.id
        	LEFT JOIN direct_equity_live_contacts.contacts Contact ON Client.contact_id = Contact.id
        	LEFT JOIN direct_equity_live_contacts.emails Email ON Contact.id = Email.contact_id
            WHERE  " .$this->sql_cond."
			GROUP BY  Account_type.type, Email.contact_type_id, Account.code, Client.code	
		";
		
		$data_CLI_EMA 	= $this -> Account -> query($SQL_CLI_EMA);
		
		foreach($data_CLI_EMA as $row)
		{
			if(empty($row['Email']['address'])) continue;
		
			if($row['Account_Type']['type']=="INDIVIDUAL" && !empty($row['Client']['code']))	
			{
				$this->record_count++;
				
				fputs($this->fp, 
					$this->check_type("EMA","a") . ','.
					$this->check_type($this->epi_version,"a"). ','.
					$this->check_type($this->provider_code,"a").','.
					$this->check_type(('EXT' .$this->adv_code),"a"). ','.
					$this->check_type($this->adv_code,"a"). ','.
					$this->check_type($row['Client']['code'],"a") . ','.
					$this->check_type("CLI","a") . ','.
					$this->check_type("Personal","a") . ','.
					$this->check_type($row['Email']['address'],"a").','.
					$this->check_type("T","a") .	
					"\r\n");
				
				$ema_second_client_id 	= $row['Account']['second_client_id']; 
				
				if(empty($ema_second_client_id)) continue;
																						
				$SQL_EMA2 = "
					SELECT Client.code, Email.address	
					FROM `clients` Client 
					LEFT JOIN direct_equity_live_contacts.contacts Contact ON Client.contact_id = Contact.id
					LEFT JOIN direct_equity_live_contacts.emails Email ON Contact.id = Email.contact_id		
					WHERE Client.id = $ema_second_client_id
                ";
							
				$data_EMA2 = $this -> Account -> query($SQL_EMA2);
							
				foreach($data_EMA2 as $row)
				{
					if(empty($row['Email']['address'])) continue;
										
					$this->record_count++;
					
					fputs($this->fp, 
					$this->check_type("EMA","a") . ','.
					$this->check_type($this->epi_version,"a"). ','.
					$this->check_type($this->provider_code,"a").','.
					$this->check_type(('EXT' .$this->adv_code),"a"). ','.
					$this->check_type($this->adv_code,"a"). ','.
					$this->check_type($row['Client']['code'],"a") . ','.
					$this->check_type("CLI","a") . ','.
					$this->check_type("Personal","a") . ','.
					$this->check_type($row['Email']['address'],"a").','.
					$this->check_type("T","a") .		
						"\r\n");				
				}										
			}
			elseif(!empty($row['Account']['code']))
			{					
				$this->record_count++;		
				
				fputs($this->fp, 
					$this->check_type("EMA","a") . ','.
					$this->check_type($this->epi_version,"a"). ','.
					$this->check_type($this->provider_code,"a").','.
					$this->check_type(('EXT' .$this->adv_code),"a"). ','.
					$this->check_type($this->adv_code,"a"). ','.
					$this->check_type($row['Account']['code'],"a") . ','.
					$this->check_type("CLI","a") . ','.
					$this->check_type("Personal","a") . ','.
					$this->check_type($row['Email']['address'],"a").','.
					$this->check_type("T","a") .	
					"\r\n");						
			}						
		}				
		$row['Email']['address']	= "";
		$row['Client']['code']		= "";
		$row['Account']['code']		= "";
		$row['Account_Type']['type']= "";		
	}
	
	function iress_acc(){				// Account
		$SQL_ACC = "
			SELECT Client.code, Account.code, Account.name, Account.client_id, 
					Account.second_client_id, Portfolio.id, Mandate.id, Mandate.code, Mandate.name, Account_Type.type 
			FROM `portfolios` Portfolio
			LEFT JOIN `mandates` Mandate ON Portfolio.mandate_id = Mandate.id
			LEFT JOIN `accounts` Account ON Portfolio.account_id = Account.id
	        LEFT JOIN `clients` Client ON Account.client_id = Client.id 
	        LEFT JOIN `advisors` Advisor ON Client.advisor_id = Advisor.id		
	        LEFT JOIN `account_types` Account_Type ON Account.account_type_id = Account_Type.id		
	        WHERE  " .$this->sql_cond."
			GROUP BY Portfolio.id
		";
		
		$data_ACC 		= $this -> Account -> query($SQL_ACC);
		
		/*------ Account -----------------------------------*/
		
		foreach($data_ACC as $row)
		{			
			$acc_delete = "F";
			
			if(empty($row['Portfolio']['id']) && $row['Account']['name'].$row['Mandate']['name'] !="") continue;
		
			if($row['Account_Type']['type']=="INDIVIDUAL")
			{
				$tax_structure = "Personal";
					
			}elseif($row['Account_Type']['type']=="FUND")
			{
				$tax_structure = "Superannuation";
			}else
			{
				$tax_structure = "Unknown";
			};
							
			$epi_prod_code= $this->epi_prod_codes[$this->provider_id][$row['Mandate']['id']];
			
			$this->record_count++;
			
			fputs($this->fp, 
				$this->check_type("ACC","a") . ','.
				$this->check_type($this->epi_version,"a"). ','.
				$this->check_type($this->provider_code,"a").','.
				$this->check_type(('EXT' .$this->adv_code),"a"). ','.
				$this->check_type($this->adv_code,"a"). ','.
				$this->check_type($row['Portfolio']['id'],"a") . ','.
				 ','.
				$this->check_type($epi_prod_code,"a") . ','.
				$this->check_type(($row['Account']['name'] .' '. $row['Mandate']['name']),"a").','.
				','.	//$this->check_type(($row['Account']['name'] .' '. $row['Mandate']['code']),"a").','.
				$this->check_type($tax_structure,"a") . ','.
				$this->check_type($acc_delete,"a") .	
				"\r\n");
		
			$epi_prod_code 		= "";
		}
		$row['Portfolio']['id']	= "";
		$row['Account']['name']	= "";
		$row['Mandate']['name']	= "";
		$row['Mandate']['code']	= "";		
	}
	
	function iress_aco()				//Account Owners
	{

		$SQL_ACO = "
			SELECT Client.code, Account.code, Account.name, Account.client_id, 
					Account.second_client_id, Portfolio.id, Account_Type.type 
			FROM `portfolios` Portfolio
			LEFT JOIN `mandates` Mandate ON Portfolio.mandate_id = Mandate.id
			LEFT JOIN `accounts` Account ON Portfolio.account_id = Account.id
	        LEFT JOIN `clients` Client ON Account.client_id = Client.id 
	        LEFT JOIN `advisors` Advisor ON Client.advisor_id = Advisor.id		
	        LEFT JOIN `account_types` Account_Type ON Account.account_type_id = Account_Type.id		
	        WHERE  " .$this->sql_cond."
			GROUP BY Portfolio.id
		";
		
		$data_ACO 		= $this -> Account -> query($SQL_ACO);
							
		foreach($data_ACO as $row)
		{
			$aco_delete = "F";	
			
			if(empty($row['Portfolio']['id'])) continue;
					
			if($row['Account_Type']['type']=="INDIVIDUAL" && !empty($row['Client']['code']))
			{
				$this->record_count++;
											
				fputs($this->fp, 
					$this->check_type("ACO","a") . ','.
					$this->check_type($this->epi_version,"a"). ','.
					$this->check_type($this->provider_code,"a").','.
					$this->check_type(('EXT' .$this->adv_code),"a"). ','.
					$this->check_type($this->adv_code,"a"). ','.
					$this->check_type($row['Portfolio']['id'],"a") . ','.
					$this->check_type($row['Client']['code'],"a") . ','.
					','.
					$this->check_type($aco_delete,"a") .	
					"\r\n");

				$aco_second_client_id 	= $row['Account']['second_client_id']; 
				$aco_portfolio_id		= $row['Portfolio']['id'];
				
				if(empty($aco_second_client_id)) continue;
				
				$SQL_AC2 = "
					SELECT Client.code
					FROM `clients` Client
					WHERE Client.id = $aco_second_client_id
                ";
							
				$data_AC2 = $this -> Account -> query($SQL_AC2);
							
				foreach($data_AC2 as $row)
				{
					$this->record_count++;
					fputs($this->fp, 
						$this->check_type("ACO","a") . ','.
						$this->check_type($this->epi_version,"a"). ','.
						$this->check_type($this->provider_code,"a").','.
						$this->check_type(('EXT' .$this->adv_code),"a"). ','.
						$this->check_type($this->adv_code,"a"). ','.
						$this->check_type($aco_portfolio_id,"a") . ','.
						$this->check_type($row['Client']['code'],"a") . ','.
						','.
						$this->check_type($aco_delete,"a") .	
						"\r\n");				
				};
				
			}elseif(!empty($row['Account']['code']))
			{				
				$this->record_count++;
											
				fputs($this->fp, 
					$this->check_type("ACO","a") . ','.
					$this->check_type($this->epi_version,"a"). ','.
					$this->check_type($this->provider_code,"a").','.
					$this->check_type(('EXT' .$this->adv_code),"a"). ','.
					$this->check_type($this->adv_code,"a"). ','.
					$this->check_type($row['Portfolio']['id'],"a") . ','.
					$this->check_type($row['Account']['code'],"a") . ','.
					','.
					$this->check_type($aco_delete,"a") .	
					"\r\n");						
			}			
		}
		$row['Portfolio']['id']				= "";
		$row['Account']['code']				= "";
		$row['Account']['name']				= "";
		$row['Account']['second_client_id']	= "";
		
		$aco_second_client_id 				= ""; 
		$aco_dealer_prov_code				= "";
		$aco_advisor_code					= "";
		$aco_portfolio_id					= "";
	}	
	
	function iress_iht_cash(){			// Investment Holding Cash Transaction
		$SQL_IHT_CASH = "
			SELECT Client.id, Account.id, Portfolio.id,
					Transaction.id,	Transaction.trade_balance_date, Transaction.value, Transaction.actual_balance_date, Transaction.description, 
					Transaction.ref, Transaction.sign, Transaction.transaction, Transaction.stock_code, Transaction.qty,
					abc.id, abc.reporting_date" 
			.$this->sql_from."
	        LEFT JOIN `de_cash_internal_transactions` Transaction ON Portfolio.id = Transaction.portfolio_id
	       	LEFT JOIN `abc_reporting` abc ON Transaction.id = abc.id
	        WHERE  " .$this->sql_cond." AND
	        		Transaction.trade_balance_date <= '".$this->requestedDate."' AND
            		(Transaction.trade_balance_date = '".$this->requestedDate."' OR	abc.id IS NULL OR abc.id ='') AND
               		Transaction.transaction NOT IN ('UNKN','OPEN','CLOS','ADJT','TFIN','TFOT','FRZE','SETL')
            GROUP BY Transaction.id            
		";
		
		$data_IHT_CASH 		= $this -> Account -> query($SQL_IHT_CASH);
				
		foreach($data_IHT_CASH as $row)
		{
			$transaction_id = $row['Transaction']['id'];				
			$iht_delete = "F";
			$key = $row['Transaction']['transaction']."-".$row['Transaction']['sign'];
			
			// If case of unknown transaction type proceed to the next transaction record
			if(!array_key_exists($key, $this->cashTransactions)) continue;	
			
			// Do we report transaction or not
			list($transactionCode, $defaultDescription, $factor) = $this->cashTransactions[$key]; 
			//if(!empty($row['abc']['id']) || $row['Transaction']['trade_balance_date'] != $this->requestedDate) continue;
									
			//Transaction Status
			$transaction_status = "Unconfirmed";		
			if(($this->dateconvert($row['Transaction']['actual_balance_date'],3)) <= ($this->dateconvert($this->requestedDate,3)))
			{
				$transaction_status = "Actual";											
				// create a record in abc_reporting		
				$SQL_abc 	= "INSERT IGNORE INTO abc_reporting (id, reporting_date) values(".$transaction_id.", '".$this->requestedDate."')";
				$data_abc	= $this -> Account -> query($SQL_abc);			
			}
						
			// reverse ID			
			/*
			$reversible1 = array("DEPO-DR", "TAXW-CR");
			$reverse_id = (in_array($key, $reversible1)) ? 
			$reverse_id = substr($row['Transaction']['ref'],3) : "";
			*/
			
			$reversible = array("DEPO-DR", "FEES-CR", "TAXW-CR");			
			$reverse_id = (in_array($key, $reversible) && substr($row['Transaction']['ref'],0,3) == "INT") ? 
			$reverse_id = substr($row['Transaction']['ref'],3) : "";
					
			// Default description	
			$transaction_description =  $row['Transaction']['description'];
			if($key =="SELL-DR"){
				$transaction_description = "Brokerage charge";
			}elseif(empty($transaction_description))
			{							
				$defaultDescription = preg_replace("/\s*%qty%\s*/", " ".round($row['Transaction']['qty'])." ", $defaultDescription);
				$defaultDescription = preg_replace("/\s*%stock_code%\s*/", " ".$row['Transaction']['stock_code'], $defaultDescription);
				$transaction_description = $defaultDescription;
			}
					
			$this->record_count++;
			
			fputs($this->fp,
			 	$this->check_type("IHT","a") . ','.
				$this->check_type($this->epi_version,"a"). ','.
				$this->check_type($this->provider_code,"a").','.
				$this->check_type(('EXT' .$this->adv_code),"a"). ','.
				$this->check_type($this->adv_code,"a"). ','.
				$this->check_type($row['Portfolio']['id'],"a") . ','.
				$this->check_type("WWCASH","a") . ','.
				','.
				$this->check_type($row['Transaction']['id'],"a") . ','.
				$this->check_type($transactionCode,"a") . ','.
				$this->check_type($this->dateconvert($row['Transaction']['trade_balance_date'],2),"n").','.
				','.
				$this->check_type(strval($factor*$row['Transaction']['value']),"n") . ','.
				$this->check_type(strval($factor*$row['Transaction']['value']),"n") . ','.
				$this->check_type("0","n") . ','.
				$this->check_type($transaction_status,"a") . ','.
				$this->check_type($this->dateconvert($row['Transaction']['actual_balance_date'],2),"n").','.
				','.
				','.
				','.
				','.
				','.
				','.
				','.
				','.
				','.
				','.
				$this->check_type($transaction_description,"a") . ','.
				','.
				','.
				$this->check_type($reverse_id ,"a") . ','.
				$this->check_type($iht_delete,"a") . 	
				"\r\n");
			
			// Record IPD(Invesment Product Record) if IHT records avaialable			
			$this->iress_ipd("WWCASH","CASH","XB");										
		};				
		
		$transactionCode 			="";
		$trans_code 				="";
		$transaction_description	="";
		$transaction_status			="";
		$reverse_id 				="";
		$transaction_id				="";					
		
		$row['Portfolio']['id']		="";
		$row['Transaction']['id'] 	="";	
	}

	function iress_iht_stock(){			// Investment Holding Stock Transaction
		
		$SQL_IHT_STOCK = "
			SELECT Client.id, Account.id, Portfolio.id, Account_Trade.id, Account_Trade.quantity, Account_Trade.avg_price,
					Account_Trade.brokerage, Account_Trade.contract_date ,Account_Trade.settle_date, Account_Trade.type,
					Asxe.ASXCode, Asxe.company, abc.id, abc.reporting_date, 
					(Account_Trade.avg_price * Account_Trade.quantity) AS Price " 
			.$this->sql_from."
			LEFT JOIN `account_trades` Account_Trade ON Account.id = Account_Trade.account_id
			LEFT JOIN `asxes` Asxe ON Account_Trade.asx_id = Asxe.id
			LEFT JOIN `abc_reporting` abc ON Account_Trade.id = abc.id
	        WHERE  " .$this->sql_cond." AND
	        		Account_Trade.contract_date <= '".$this->requestedDate."' AND
            		(Account_Trade.contract_date = '".$this->requestedDate."' OR	abc.id IS NULL OR abc.id ='') AND
               		Account_Trade.type IN ('Buy', 'IntBuy', 'Sell', 'IntSell', 'TransferIn', 'TransferOut', 'SwitchIn', 'SwitchOut',
               		'CorpConsolidation', 'CorpSplit')
            GROUP BY Account_Trade.id
            ORDER BY Asxe.ASXCode         
		";
		
		$data_IHT_STOCK 		= $this -> Account -> query($SQL_IHT_STOCK);				
		$ipd_rec				= false;			
		
		foreach($data_IHT_STOCK as $row)
		{		
			$transaction_id = $row['Account_Trade']['id'];				
			$iht_delete = "F";
			$key = $row['Account_Trade']['type'];
			
			// If case of unknown transaction type proceed to the next transaction record
			if(!array_key_exists($key, $this->stockTransactions)) continue;	
			
			// Do we report transaction or not
			list($transactionCode, $factor, $gross_val, $net_val, $cost_base, $brokerage) = $this->stockTransactions[$key]; 
			//if(!empty($row['abc']['id']) || $row['Account_Trade']['contract_date'] != $this->requestedDate) continue;
								
			//Transaction Status
			$transaction_status = "Unconfirmed";		
			if(($this->dateconvert($row['Account_Trade']['settle_date'],3)) <= ($this->dateconvert($this->requestedDate,3)))
			{
				$transaction_status = "Actual";											
				// create a record in abc_reporting		
				$SQL_abc 	= "INSERT IGNORE INTO abc_reporting (id, reporting_date) values(".$transaction_id.", '".$this->requestedDate."')";
				$data_abc	= $this -> Account -> query($SQL_abc);			
			}
			
			$gross_val 	= preg_replace("/\s*%ap_qty_pbk%\s*/" , " ".($row['0']['Price']+ $row['Account_Trade']['brokerage'])." ", $gross_val);
			$gross_val 	= preg_replace("/\s*%ap_qty%\s*/" 	  , " ".$row['0']['Price']." "	, $gross_val);
			$gross_val 	= strval(round(($gross_val * $factor),2));
			
			if($factor=="-1" && abs($row['0']['Price']) < $row['Account_Trade']['brokerage'])
			{
				$net_val = "0";
				$brokerage	= round(abs(($gross_val)/1.1),2);
			}else
			{
				$net_val 	= preg_replace("/\s*%ap_qty_nbk%\s*/" , " ".($row['0']['Price'] - $row['Account_Trade']['brokerage'])." ", $net_val);
				$net_val 	= preg_replace("/\s*%ap_qty%\s*/"	  , " ".$row['0']['Price']." "	, $net_val);
				
				$brokerage 	= round(((preg_replace("/\s*%bk%\s*/" , " ".$row['Account_Trade']['brokerage']." " , $brokerage))/1.1),2);						
			}			
			$net_val 	= strval(round(($net_val * $factor),2));
			
			$cost_base 	= preg_replace("/\s*%ap_qty_pbk%\s*/" , " ".($row['0']['Price']+ $row['Account_Trade']['brokerage'])." ", $cost_base);	
			$cost_base 	= preg_replace("/\s*%ap_qty%\s*/" 	  , " ".$row['0']['Price']." "	, $cost_base);
			$cost_base 	= strval(round(($cost_base * $factor),2));
					
			$gst_brokerage = !empty($brokerage)? 10: "";		
			$this->record_count++;
			
			fputs($this->fp,
			 	$this->check_type("IHT","a") . ','.
				$this->check_type($this->epi_version,"a"). ','.
				$this->check_type($this->provider_code,"a").','.
				$this->check_type(('EXT' .$this->adv_code),"a"). ','.
				$this->check_type($this->adv_code,"a"). ','.
				$this->check_type($row['Portfolio']['id'],"a") . ','.
				$this->check_type($row['Asxe']['ASXCode'],"a") . ','.
				','.
				$this->check_type($row['Account_Trade']['id'],"a") . ','.
				$this->check_type($transactionCode,"a") . ','.
				$this->check_type($this->dateconvert($row['Account_Trade']['contract_date'],2),"n").','.
				$this->check_type(round(($row['Account_Trade']['quantity'] * $factor),2),"n").','.
				$this->check_type($gross_val,"n") . ','.
				$this->check_type($net_val,"n") . ','.
				$this->check_type($cost_base,"n") . ','.
				$this->check_type($transaction_status,"a") . ','.
				$this->check_type($this->dateconvert($row['Account_Trade']['settle_date'],2),"n").','.
				','.
				','.
				','.
				','.
				','.
				','.
				','.
				','.
				$this->check_type($brokerage,"n") . ','.
				$this->check_type($gst_brokerage,"n") . ','.
				','.
				','.
				','.
				','.
				$this->check_type($iht_delete,"a") . 	
				"\r\n");
			
			// Create IPD records for each unique Invesment Code
			$this->iress_ipd($row['Asxe']['ASXCode'],$row['Asxe']['company'],"DS");
		}
	}

	function iress_ihb_cash()			// Investment Holding Blance - Cash 			
	{						
				
		//Retrieve Trade and Actual Blances
		$ActualCashBalance 	= $this->AccountActivity->getPortfolioActualCashBalance	(null, $this->requestedDate);
		$TradeCashBalance 	= $this->AccountActivity->getPortfolioTradeCashBalance	(null, $this->requestedDate);
		
		$SQL_IHB_CASH = "
				SELECT Portfolio.id" 
				.$this->sql_from."
				WHERE " .$this->sql_cond."
				GROUP BY Portfolio.id	
				";
		
		$data_IHB_CASH 	= $this -> Account -> query($SQL_IHB_CASH);
	
		foreach($data_IHB_CASH as $row)
		{				
			if	((!array_key_exists($row['Portfolio']['id'], $TradeCashBalance)) && 
				( !array_key_exists($row['Portfolio']['id'], $ActualCashBalance))) continue;				
			
			$Tbalance = $TradeCashBalance[$row['Portfolio']['id']];
			$Abalance = $ActualCashBalance[$row['Portfolio']['id']];		
			
			$this->record_count++;
			
			fputs($this->fp,
			 	$this->check_type("IHB","a") . ','.
				$this->check_type($this->epi_version,"a"). ','.
				$this->check_type($this->provider_code,"a").','.
				$this->check_type(('EXT' .$this->adv_code),"a"). ','.
				$this->check_type($this->adv_code,"a"). ','.
				$this->check_type($row['Portfolio']['id'],"a") . ','.
				$this->check_type("WWCASH","a") . ','.
				$this->check_type($row['Portfolio']['id'],"a") . ','.
				$this->check_type(strval(round($Abalance,2)),"n") . ','.
				$this->check_type(strval(round(($Tbalance - $Abalance),2)),"n") . ','.
				$this->check_type($this->as_at_date,"n") .	
				"\r\n");
			
			$this->iress_ipd("WWCASH","CASH","XB");								
		}			
	}
	
	function iress_ihb_stock(){			// Investment Holding Blance - Stock
		$SQL_IHB_STOCK = "
			SELECT 	pid,code,company, 
					SUM(IF(status = 'SETTLED', qty, 0)) as actual, 
					SUM(IF(status = 'UNSETTLED', qty, 0)) as unconfirmed  
			FROM 
			(
				SELECT 	portfolio_id as pid, ASX.ASXCode as code, ASX.company as company,
						IF(T.type IN ('Sell', 'TransferOut', 'SwitchOut', 'IntSell', 'CorpConsolidation'), quantity*-1, quantity) as qty, 
						IF(settle_date > '".$this->requestedDate."', 'UNSETTLED', 'SETTLED') as status, contract_date, settle_date" 
				.$this->sql_from."				
        		LEFT JOIN `direct_equity_live`.`account_trades` T 	ON Account.id = T.account_id
				LEFT JOIN `direct_equity_live`.`asxes` 			ASX ON ASX.id = T.asx_id 
				WHERE " .$this->sql_cond." AND T.contract_date <= '".$this->requestedDate."' 
			) Trades 
			GROUP BY pid, code 
			HAVING actual <> 0 OR unconfirmed <> 0         
		";		
		$data_IHB_STOCK 		= $this -> Account -> query($SQL_IHB_STOCK);	
		
		foreach($data_IHB_STOCK as $row)
		{
			$this->record_count++;
			
			fputs($this->fp,
			 	$this->check_type("IHB","a") . ','.
				$this->check_type($this->epi_version,"a"). ','.
				$this->check_type($this->provider_code,"a").','.
				$this->check_type(('EXT' .$this->adv_code),"a"). ','.
				$this->check_type($this->adv_code,"a"). ','.
				$this->check_type($row['Trades']['pid'],"a") . ','.
				$this->check_type($row['Trades']['code'],"a") . ','.
				$this->check_type($row['Trades']['pid'],"a") . ','.
				$this->check_type($row['0']['actual'],"n") . ','.
				$this->check_type($row['0']['unconfirmed'],"n") . ','.
				$this->check_type($this->as_at_date,"n") .	
				"\r\n");
		
		$this->iress_ipd($row['Trades']['code'],$row['Trades']['company'],"DS");
		}		
	}
	
	function iress_ipd($ipd_code, $ipd_name, $ipd_type)		// Create IPD records for each unique Invesment Code
	{		
		if (!in_array($ipd_code, $this->ipd_codes))
		{
		    array_push($this->ipd_codes, $ipd_code);
		    
		    $this->record_count++;			
			fputs($this->fp, 
				$this->check_type("IPD","a") . ','.
				$this->check_type($this->epi_version,"a"). ','.
				$this->check_type($this->provider_code,"a").','.
				$this->check_type(('EXT' .$this->adv_code),"a"). ','.
				$this->check_type($ipd_code,"a") . ','.
				$this->check_type($ipd_name,"a") . ','.
				$this->check_type($ipd_type,"a") . ','.
				$this->check_type("Unknown","a"). ','.
				$this->check_type("F","a") .
				','.
				','.
				','.
				','.
				','.
				','.
				','.
				','.
				','.
				','.
				','.
				','.
				','.
				','.	
				"\r\n");	
		}		
	}

	function iress_trl(){				// Tailor
		fputs($this->fp, 
			$this->check_type("TRL","a") . ','.
			$this->check_type($this->epi_version,"a"). ','.
			$this->check_type($this->record_count,"n") .	
			"\r\n");	
	}
 }
?>
