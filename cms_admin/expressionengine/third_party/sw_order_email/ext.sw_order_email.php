<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * Update Donation amount for sorting purposes
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Extension
 * @author		Kevin Chatel
 * @link		http://www.signatureweb.ca
 */

class Sw_order_email_ext {
	
	public $settings 		= array();
	public $description		= 'Once the order is placed we email the people who paid by credit card their tax receipt.';
	public $docs_url		= '';
	public $name			= 'Order Tax Email';
	public $settings_exist	= 'n';
	public $version			= '1.0';
	
	private $EE;
	
	/**
	 * Constructor
	 *
	 * @param 	mixed	Settings array or empty string if none exist.
	 */
	public function __construct($settings = '')
	{
		$this->EE =& get_instance();
		$this->settings = $settings;
	}// ----------------------------------------------------------------------
	
	/**
	 * Activate Extension
	 *
	 * This function enters the extension into the exp_extensions table
	 *
	 * @see http://codeigniter.com/user_guide/database/index.html for
	 * more information on the db class.
	 *
	 * @return void
	 */
	public function activate_extension()
	{
		// Setup custom settings in this array.
		$this->settings = array();
		
		$data = array(
			'class'		=> __CLASS__,
			'method'	=> 'email',
			'hook'		=> 'store_order_complete_end',
			'settings'	=> serialize($this->settings),
			'version'	=> $this->version,
			'enabled'	=> 'y'
		);

		$this->EE->db->insert('extensions', $data);
		
	}

	// ----------------------------------------------------------------------
	
	/**
	* check
	*
	* @param
	* @return
	*/

	public function email($order)
	{
		
		ee()->load->library('session');
		ee()->load->helper('url');
		
		$language = ee()->session->userdata('language');
		
		// Load the email library
		ee()->load->library('email');
		//Load the email Helper
		ee()->load->helper('text');
		
		ee()->load->helper('date');
		
		$datestring = "%F %d, %Y"; /* at %h:%i %a */
		

		$team = $order["items"][0]["title"].' ('.$this->driver_name($order["items"][0]["entry_id"]).$this->navigator_name($order["items"][0]["entry_id"]).')';

		/** English Body **/
		
		// If general donation or team donation
		
		
		if($language == 'french') {
			
			$subject = 'Remerciements pour votre don dans le cadre du Rallye Sina&iuml;';
			
			setlocale(LC_TIME, "fr_FR");
			
			$date = strftime("%A le %d %B, %Y", $order["order_date"]);
			
			$body = '
				<!DOCTYPE HTML>
				<html>
				<head>
				<!-- Basic Page Needs
				  ================================================== -->
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
				<title>Sinai Rally / Rallye Sinai</title>
				<meta name="description" content="">
				<meta name="keywords" content="">
				<meta name="author" content="http://www.signatureweb.ca/">
				<link href="{site_url}assets/css/pdf.css" rel="stylesheet" media="pdf">
				<style type="text/css">
				body {
					background-color:#F8F7F3;
				}
				body,td,th {
					font-family: Gotham, "Helvetica Neue", Helvetica, Arial, sans-serif;
					font-size:14px;
				}
				</style>
				</head>

				<body>
		
			<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#F8F7F3">';
			$body = $body.'  <tbody>';
			$body = $body.'    <tr>';
			$body = $body.'      <td align="center" valign="top" style="margin-top: 20px; padding-top: 20px; padding-bottom: 20px;"><table width="650" border="0" cellspacing="0" cellpadding="0">';
			$body = $body.'        <tbody>';
			$body = $body.'          <tr>';
			$body = $body.'            <td align="right" style="text-align:right; background-color:#C71B1B"><table border="0" align="right" cellpadding="5" cellspacing="0">';
			$body = $body.'              <tbody>';
			$body = $body.'                <tr>';
			$body = $body.'                  <td><a href="http://www.facebook.com/SinaiRally"><img src="'.base_url().'assets/images/email/facebook.png" width="37" height="37" alt=""/></a></td>';
			$body = $body.'                  <td><a href="https://twitter.com/sinairally"><img src="'.base_url().'assets/images/email/twitter.png" width="37" height="37" alt=""/></a></td>';
			$body = $body.'                  <td><a href="http://www.youtube.com/channel/UC3JCRvwJoKEWQK_IIo6iYvg" target="_blank"><img src="'.base_url().'assets/images/email/youtube.png" width="37" height="37" alt=""/></a></td>';
			$body = $body.'                  </tr>';
			$body = $body.'              </tbody>';
			$body = $body.'            </table></td>';
			$body = $body.'          </tr>';
			$body = $body.'          <tr>';
			$body = $body.'            <td><img src="'.base_url().'assets/images/email/header.jpg" width="650" height="135" alt=""/></td>';
			$body = $body.'          </tr>';
			$body = $body.'          <tr>';
			$body = $body.'            <td bgcolor="#FFFFFF" style="padding:20px;text-align:left;">';
			$body = $body.'            <p>Bonjour '.$order["billing_first_name"].',</p>';
		
			if($order['items'][0]['title'] == 'General Donation') {
				$body = $body.'<p>Merci pour votre don de <strong>'.number_format($order["order_total"], 2, ".", "").'$, inscrit '.$date.'</strong> dans le cadre du Rallye Sina&iuml; de la Fondation de l’Hôpital Mont-Sina&iuml;. Votre don est important pour nous et fait une différence fondamentale dans la vie de nos patients et de leurs familles.</p>';
			} else {
				$body = $body.'<p>Merci pour votre don de <strong>'.number_format($order["order_total"], 2, ".", "").'$, inscrit '.$date.'</strong> dans le cadre du Rallye Sina&iuml; de la Fondation de l’Hôpital Mont-Sina&iuml;, afin de soutenir l’équipe de <strong>Team '.$team.'</strong>.  Votre don est important pour nous et fait une différence fondamentale dans la vie de nos patients et de leurs familles.</p>';
			}

			if($order['payment_method'] == 'manual') {
				$body = $body.'<p>Vous avez choisi l’option « Envoyez-moi une facture ». Par conséquent, cliquez sur <a href="'.base_url().'fr/account/invoice-pdf/'.$order["order_hash"].'">ce lien</a> pour voir et imprimer votre facture officielle. Votre reçu aux fins de l’impôt sur le revenu vous sera envoyé une fois que vous aurez acquitté votre promesse de don.</p>';
			} else {
				$body = $body.'<p>You can download your Tax Reciept by clicking <a href="'.base_url().'fr/account/tax-receipt/'.$order["order_hash"].'">this link</a>.</p>';
			}
			$body = $body.'<p>Pour de plus amples renseignements au sujet de l’événement, visitez le site <a href="http://www.sinairally.org">www.sinairally.org</a>. Pour en savoir plus sur le travail important de la Fondation de l’Hôpital Mont-Sina&iuml;, visitez-nous à l’adresse <a href="http://www.mountsinaifoundation.org">www.mountsinaifoundation.org</a>.</p>';
			$body = $body.'<p>Merci encore pour votre soutien.</p>';
			$body = $body.'<p><strong>Fondation de l’Hôpital Mont-Sina&iuml;</strong><br />Organisme de bienfaisance enregistré sous le numéro : <em>11892 4331 RR0001</em></p>';
			$body = $body.'<p>&nbsp;</p>';
			$body = $body.'<p>P.S.: Veuillez noter que votre facture est en format PDF et que vous aurez besoin du logiciel Adobe Acrobat Reader pour afficher le document. Si ce programme n’est pas déjà installé sur votre ordinateur, visitez le site <a href="http://get.adobe.com/reader/">www.adobe.com</a> pour le télécharger gratuitement.</p>';
			$body = $body.'<p>P.P.S.:  Si vous éprouvez des problèmes à afficher ou à imprimer votre facture, communiquez avec la Fondation en composant le 514 369-2222, poste 1299.</p>';
			$body = $body.'<p>&nbsp;</p>';
			$body = $body.'<p>&nbsp;</p>';
			$body = $body.'<h3>Détails</h3>';

			$body = $body.'<table width="100%" border="0" cellspacing="0" cellpadding="10">';
			$body = $body.'  <tbody>';
			$body = $body.'    <tr>';
			$body = $body.'      <td bgcolor="#FFEDED">';
			$body = $body.'      <table width="100%" border="0" cellspacing="0" cellpadding="0">';
			$body = $body.'  <tbody>';
			$body = $body.'    <tr>';
			$body = $body.'      <td width="30%">Au nom de: </td>';
			$body = $body.'      <td width="70%">'.$order["billing_first_name"].' '.$order["billing_last_name"].'</td>';
			$body = $body.'    </tr>';
			$body = $body.'    <tr>';
			$body = $body."     <td width='30%'>Reçu d'impôt pour:</td>";
			$body = $body.'      <td width="70%">'.$order["order_custom3"].'</td>';
			$body = $body.'    </tr>';
			$body = $body.'    <tr>';
		
			$body = $body."      <td width='30%'>À l'appui de:</td>";
			if($order['items'][0]['title'] == 'General Donation') {
				$body = $body.'      <td width="70%">Rallye Sina&iuml; de la Fondation de l’Hôpital Mont-Sina&iuml;</td>';
			} else {
				$body = $body.'      <td width="70%">'.$order["items"][0]["title"].' ('.$this->driver_name($order["items"][0]["entry_id"]).')</td>';
			}
			$body = $body.'    </tr>';
			$body = $body.'    <tr>';
			$body = $body.'      <td width="30%">Date:</td>';
			$body = $body.'      <td width="70%">'.$date.'</td>';
			$body = $body.'    </tr>';
			$body = $body.'    <tr>';
			$body = $body.'      <td width="30%">Montant:</td>';
			$body = $body.'      <td width="70%">'.number_format($order["order_total"], 2, ".", "").'$</td>';
			$body = $body.'    </tr>';
			$body = $body.'    <tr>';
			$body = $body.'      <td width="30%">Mode de paiement:</td>';
		
			if($order['payment_method'] == 'manual') {
				$body = $body."<td width='70%'>Envoyez-moi une facture</td>";
			} else {
				$body = $body."<td width='70%'>Carte de credit</td>";
			}
		
			$body = $body.'    </tr>';
			$body = $body.'  </tbody>';
			$body = $body.'</table>';

			$body = $body.'      </td>';
			$body = $body.'    </tr>';
			$body = $body.'  </tbody>';
			$body = $body.'</table>';

			$body = $body.'            </td>';
			$body = $body.'          </tr>';
			$body = $body.'          <tr>';
			$body = $body.'            <td bgcolor="#ECEAE4" style="border-top:#d8d8d8 2px solid;padding:20px">Rallye Sina&iuml;. Tous les droits sont réservés</td>';
			$body = $body.'          </tr>';
			$body = $body.'        </tbody>';
			$body = $body.'      </table></td>';
			$body = $body.'    </tr>';
			$body = $body.'  </tbody>';
			$body = $body.'</table></body></html>';
			
			
			
		} else {
			
			$subject = 'Thank you for your donation to Sinai Rally';
			
			$body = '
				<!DOCTYPE HTML>
				<html>
				<head>
				<!-- Basic Page Needs
				  ================================================== -->
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
				<title>Sinai Rally / Rallye Sinai</title>
				<meta name="description" content="">
				<meta name="keywords" content="">
				<meta name="author" content="http://www.signatureweb.ca/">
				<link href="{site_url}assets/css/pdf.css" rel="stylesheet" media="pdf">
				<style type="text/css">
				body {
					background-color:#F8F7F3;
				}
				body,td,th {
					font-family: Gotham, "Helvetica Neue", Helvetica, Arial, sans-serif;
					font-size:14px;
				}
				</style>
				</head>

				<body>
		
			<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#F8F7F3">';
			$body = $body.'  <tbody>';
			$body = $body.'    <tr>';
			$body = $body.'      <td align="center" valign="top" style="margin-top: 20px; padding-top: 20px; padding-bottom: 20px;"><table width="650" border="0" cellspacing="0" cellpadding="0">';
			$body = $body.'        <tbody>';
			$body = $body.'          <tr>';
			$body = $body.'            <td align="right" style="text-align:right; background-color:#C71B1B"><table border="0" align="right" cellpadding="5" cellspacing="0">';
			$body = $body.'              <tbody>';
			$body = $body.'                <tr>';
			$body = $body.'                  <td><a href="http://www.facebook.com/SinaiRally"><img src="'.base_url().'assets/images/email/facebook.png" width="37" height="37" alt=""/></a></td>';
			$body = $body.'                  <td><a href="https://twitter.com/sinairally"><img src="'.base_url().'assets/images/email/twitter.png" width="37" height="37" alt=""/></a></td>';
			$body = $body.'                  <td><a href="http://www.youtube.com/channel/UC3JCRvwJoKEWQK_IIo6iYvg" target="_blank"><img src="'.base_url().'assets/images/email/youtube.png" width="37" height="37" alt=""/></a></td>';
			$body = $body.'                  </tr>';
			$body = $body.'              </tbody>';
			$body = $body.'            </table></td>';
			$body = $body.'          </tr>';
			$body = $body.'          <tr>';
			$body = $body.'            <td><a href="http://sinairally.com/"><img src="'.base_url().'assets/images/email/header.jpg" width="650" height="135" alt=""/></a></td>';
			$body = $body.'          </tr>';
			$body = $body.'          <tr>';
			$body = $body.'            <td bgcolor="#FFFFFF" style="padding:20px;text-align:left;">';
			$body = $body.'            <p>Dear '.$order["billing_first_name"].',</p>';
		
			if($order['items'][0]['title'] == 'General Donation') {
				$body = $body.'<p>Thank you for your of donation <strong>$'.number_format($order["order_total"], 2, ".", "").' on '.mdate($datestring, $order["order_date"]).'</strong> to the Mount Sinai Hospital Foundation for the Sinai Rally.  Your gift is important to us as it makes a fundamental difference in the lives of our patients and their families.</p>';
			} else {
				$body = $body.'<p>Thank you for your of donation <strong>$'.number_format($order["order_total"], 2, ".", "").' on '.mdate($datestring, $order["order_date"]).'</strong> to the Mount Sinai Hospital Foundation for the Sinai Rally in support of <strong>Team '.$team.'</strong>.  Your gift is important to us as it makes a fundamental difference in the lives of our patients and their families.</p>';
			}

			if($order['payment_method'] == 'manual') {
				$body = $body.'<p>You have chosen the “<strong>bill me</strong>” option.  Please click on <a href="'.base_url().'en/account/invoice-pdf/'.$order["order_hash"].'">this link</a> to view and print your official invoice.  Your receipt for income tax purposes will be mailed to you once your pledge has been paid.</p>';
			} else {
				$body = $body.'<p>You can download your Tax Receipt by clicking <a href="'.base_url().'en/account/tax-receipt/'.$order["order_hash"].'">this link</a>.</p>';
			}
			$body = $body.'<p>If you would like more information about the event please visit <a href="http://www.sinairally.org">www.sinairally.org</a>. If you would like to learn more about the important work Mount Sinai Foundation does please visit us at <a href="http://www.mountsinaifoundation.org">www.mountsinaifoundation.org</a>.</p>';
			$body = $body.'<p>Thank you again for your support.</p>';
			$body = $body.'<p><strong>Mount Sinai Hospital Foundation</strong><br />Charity Registration Number: <em>11892 4331 RR0001</em></p>';
			$body = $body.'<p>&nbsp;</p>';
			$body = $body.'<p>P.S.: Please note that your invoice is a PDF file and you will need Adobe Acrobat Reader in order to view it. If you do not have this program installed, please visit <a href="http://get.adobe.com/reader/">www.adobe.com</a> to download this program free of charge.</p>';
			$body = $body.'<p>P.P.S.:  If you have any problems viewing or printing your invoice please contact the Foundation office at 514 369-2222 #1299.</p>';
			$body = $body.'<p>&nbsp;</p>';
			$body = $body.'<p>&nbsp;</p>';
			$body = $body.'<h3>Donation Details</h3>';

			$body = $body.'<table width="100%" border="0" cellspacing="0" cellpadding="10">';
			$body = $body.'  <tbody>';
			$body = $body.'    <tr>';
			$body = $body.'      <td bgcolor="#FFEDED">';
			$body = $body.'      <table width="100%" border="0" cellspacing="0" cellpadding="0">';
			$body = $body.'  <tbody>';
			$body = $body.'    <tr>';
			$body = $body.'      <td width="30%">On Behalf Of: </td>';
			$body = $body.'      <td width="70%">'.$order["billing_first_name"].' '.$order["billing_last_name"].'</td>';
			$body = $body.'    </tr>';
			$body = $body.'    <tr>';
			$body = $body.'     <td width="30%">Tax Receipt To:</td>';
			$body = $body.'      <td width="70%">'.$order["order_custom3"].'</td>';
			$body = $body.'    </tr>';
			$body = $body.'    <tr>';
		
			$body = $body.'      <td width="30%">In Support Of:</td>';
			if($order['items'][0]['title'] == 'General Donation') {
				$body = $body.'      <td width="70%">Mount Sinai Hospital Foundation for the Sinai Rally</td>';
			} else {
				$body = $body.'      <td width="70%">'.$order["items"][0]["title"].' ('.$this->driver_name($order["items"][0]["entry_id"]).')</td>';
			}
			$body = $body.'    </tr>';
			$body = $body.'    <tr>';
			$body = $body.'      <td width="30%">Date:</td>';
			$body = $body.'      <td width="70%">'.mdate($datestring, $order["order_date"]).'</td>';
			$body = $body.'    </tr>';
			$body = $body.'    <tr>';
			$body = $body.'      <td width="30%">Amount:</td>';
			$body = $body.'      <td width="70%">$'.number_format($order["order_total"], 2, ".", "").'</td>';
			$body = $body.'    </tr>';
			$body = $body.'    <tr>';
			$body = $body.'      <td width="30%">Payment Method:</td>';
		
			if($order['payment_method'] == 'manual') {
				$body = $body."<td width='70%'>Bill Me / I will pay by cheque</td>";
			} else {
				$body = $body."<td width='70%'>Credit Card</td>";
			}
		
			$body = $body.'    </tr>';
			$body = $body.'  </tbody>';
			$body = $body.'</table>';

			$body = $body.'      </td>';
			$body = $body.'    </tr>';
			$body = $body.'  </tbody>';
			$body = $body.'</table>';

			$body = $body.'            </td>';
			$body = $body.'          </tr>';
			$body = $body.'          <tr>';
			$body = $body.'            <td bgcolor="#ECEAE4" style="border-top:#d8d8d8 2px solid;padding:20px">Sinai Rally. All Rights Reserved</td>';
			$body = $body.'          </tr>';
			$body = $body.'        </tbody>';
			$body = $body.'      </table></td>';
			$body = $body.'    </tr>';
			$body = $body.'  </tbody>';
			$body = $body.'</table></body></html>';
		
		}
		
		/** French Body **/
		
		
		ee()->email->wordwrap = true;
		ee()->email->mailtype = 'html';
		ee()->email->from('noreply@sinairally.org', 'Sinai Rally');
		ee()->email->to($order['order_email']);
		
		ee()->email->cc('info@sinairally.org');
		ee()->email->bcc('transactions@signatureweb.ca,kara.m@sympatico.ca,kara.maritzer@gmail.com,linda.kurbel@mountsinaifoundation.org');
		
		ee()->email->subject($subject);
		
		ee()->email->message($body);
		ee()->email->Send();
		
		$this->email_team($order);
	}
	
	function email_team($order) 
	{
		// Load the email library
		ee()->load->library('email');
		//Load the email Helper
		ee()->load->helper('text');
		
		ee()->load->helper('date');
		
		$datestring = "%F %d, %Y"; /* at %h:%i %a */
		
		
		$body = '
			<!DOCTYPE HTML>
			<html>
			<head>
			<!-- Basic Page Needs
			  ================================================== -->
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
			<title>Sinai Rally / Rallye Sinai</title>
			<meta name="description" content="">
			<meta name="keywords" content="">
			<meta name="author" content="http://www.signatureweb.ca/">
			<link href="{site_url}assets/css/pdf.css" rel="stylesheet" media="pdf">
			<style type="text/css">
			body {
				background-color:#F8F7F3;
			}
			body,td,th {
				font-family: Gotham, "Helvetica Neue", Helvetica, Arial, sans-serif;
				font-size:14px;
			}
			</style>
			</head>

			<body>
	
		<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#F8F7F3">';
		$body = $body.'  <tbody>';
		$body = $body.'    <tr>';
		$body = $body.'      <td align="center" valign="top" style="margin-top: 20px; padding-top: 20px; padding-bottom: 20px;"><table width="650" border="0" cellspacing="0" cellpadding="0">';
		$body = $body.'        <tbody>';
		$body = $body.'          <tr>';
		$body = $body.'            <td align="right" style="text-align:right; background-color:#C71B1B"><table border="0" align="right" cellpadding="5" cellspacing="0">';
		$body = $body.'              <tbody>';
		$body = $body.'                <tr>';
		$body = $body.'                  <td><a href="http://www.facebook.com/SinaiRally"><img src="'.base_url().'assets/images/email/facebook.png" width="37" height="37" alt=""/></a></td>';
		$body = $body.'                  <td><a href="https://twitter.com/sinairally"><img src="'.base_url().'assets/images/email/twitter.png" width="37" height="37" alt=""/></a></td>';
		$body = $body.'                  <td><a href="http://www.youtube.com/channel/UC3JCRvwJoKEWQK_IIo6iYvg" target="_blank"><img src="'.base_url().'assets/images/email/youtube.png" width="37" height="37" alt=""/></a></td>';
		$body = $body.'                  </tr>';
		$body = $body.'              </tbody>';
		$body = $body.'            </table></td>';
		$body = $body.'          </tr>';
		$body = $body.'          <tr>';
		$body = $body.'            <td><a href="http://sinairally.com/"><img src="'.base_url().'assets/images/email/header.jpg" width="650" height="135" alt=""/></a></td>';
		$body = $body.'          </tr>';
		$body = $body.'          <tr>';
		$body = $body.'            <td bgcolor="#FFFFFF" style="padding:20px;text-align:left;">';
		$body = $body.'            <p>Dear '.$this->driver_name($order["items"][0]["entry_id"]).',</p>';
	

		$body = $body.'				<p>You have just received a donation of <strong>$'.number_format($order["order_total"], 2, ".", "").'</strong> on behalf of <strong>'.$order["order_custom7"].' '.$order["billing_first_name"].' '.$order["billing_last_name"].'</strong> </p>';
	
		if($order['order_custom5'] != '') {
			$body = $body.'            <p><strong>The donor has left you a message:</strong></p>';
			$body = $body.'            <p><em>'.$order['order_custom5'].'</em></p>';
		}
		
		$body = $body.'            </td>';
		$body = $body.'          </tr>';
		$body = $body.'          <tr>';
		$body = $body.'            <td bgcolor="#ECEAE4" style="border-top:#d8d8d8 2px solid;padding:20px">Sinai Rally. All Rights Reserved</td>';
		$body = $body.'          </tr>';
		$body = $body.'        </tbody>';
		$body = $body.'      </table></td>';
		$body = $body.'    </tr>';
		$body = $body.'  </tbody>';
		$body = $body.'</table></body></html>';
		
		
		
		ee()->email->wordwrap = true;
		ee()->email->mailtype = 'html';
		ee()->email->from('noreply@sinairally.org', 'Sinai Rally');
		ee()->email->to($this->team_email($order["items"][0]["entry_id"]));
		if($this->has_navigator($order["items"][0]["entry_id"])) {
			ee()->email->cc($this->navigator_email($order["items"][0]["entry_id"]));
		}
		ee()->email->bcc('transactions@signatureweb.ca,kara.m@sympatico.ca,kara.maritzer@gmail.com,linda.kurbel@mountsinaifoundation.org');
		
		
		ee()->email->subject('Donation received in support of your team');
		
		ee()->email->message($body);
		ee()->email->Send();
	}

	function team_email($entryID)
	{
		/*SELECT exp_members.email
		FROM exp_members INNER JOIN exp_channel_titles ON exp_members.member_id = exp_channel_titles.author_id*/
		
		$this->EE->db->select('exp_members.email');
		$this->EE->db->from('exp_members');
		$this->EE->db->join('exp_channel_titles', 'exp_members.member_id = exp_channel_titles.author_id', 'inner');
		$this->EE->db->where('entry_id', $entryID);

		$query = $this->EE->db->get();
		$results = $query->result_array();
		
		if($query->num_rows() > 0)
		{
			return $results[0]['email'];
		}
	}


	function driver_name($entryID)
	{
		
		$this->EE->db->select('*');
		$this->EE->db->from('exp_channel_data');
		$this->EE->db->where('entry_id', $entryID);

		$query = $this->EE->db->get();
		$results = $query->result_array();
		
		if($query->num_rows() > 0)
		{
			return $results[0]['field_id_16']." ".$results[0]['field_id_17'];
		}
	}

	function navigator_email($entryID)
	{
		
		$this->EE->db->select('*');
		$this->EE->db->from('exp_channel_data');
		$this->EE->db->where('entry_id', $entryID);

		$query = $this->EE->db->get();
		$results = $query->result_array();
		
		if($query->num_rows() > 0)
		{
			return $results[0]['field_id_49'];
		}
	}
	
	function navigator_name($entryID)
	{
		
		$this->EE->db->select('*');
		$this->EE->db->from('exp_channel_data');
		$this->EE->db->where('entry_id', $entryID);

		$query = $this->EE->db->get();
		$results = $query->result_array();
		
		if($query->num_rows() > 0)
		{
			if($results[0]['field_id_35'] != '') {
				return " & ".$results[0]['field_id_35']." ".$results[0]['field_id_36'];
			}
			
		}
	}
	
	function has_navigator($entryID)
	{
		
		$this->EE->db->select('*');
		$this->EE->db->from('exp_channel_data');
		$this->EE->db->where('entry_id', $entryID);

		$query = $this->EE->db->get();
		$results = $query->result_array();
		
		if($query->num_rows() > 0)
		{
			if($results[0]['field_id_35'] != 'none' || $results[0]['field_id_35'] != NULL || $results[0]['field_id_35'] != '' || $results[0]['field_id_35'] != ' ') {
				return TRUE;
			} else {
				return FALSE;
			}
		} else {
			return FALSE;
		}
	}
	
	
	function tax_reciept($order)
	{
		
		// Load the email library
		ee()->load->library('email');
		//Load the email Helper
		ee()->load->helper('text');
		
		$body = "<h2>Donation Confirmation</h2>";
		
		$body = $body."";
		
		
		ee()->email->wordwrap = true;
		ee()->email->mailtype = 'html';
		ee()->email->from('noreply@sinairally.org', 'Sinai Rally');
		ee()->email->to($order['order_email']);
		ee()->email->bcc('transactions@signatureweb.ca');
		ee()->email->subject('Tax Reciept / Confirmation de don');
		ee()->email->message($body);
		ee()->email->Send();
		
		
	}
	

	// ----------------------------------------------------------------------

	/**
	 * Disable Extension
	 *
	 * This method removes information from the exp_extensions table
	 *
	 * @return void
	 */
	function disable_extension()
	{
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->delete('extensions');
	}

	// ----------------------------------------------------------------------

	/**
	 * Update Extension
	 *
	 * This function performs any necessary db updates when the extension
	 * page is visited
	 *
	 * @return 	mixed	void on update / false if none
	 */
	function update_extension($current = '')
	{
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}
	}	
	
	// ----------------------------------------------------------------------
}

/* End of file ext.email_after_subscription.php */
/* Location: /system/expressionengine/third_party/email_after_subscription/ext.email_after_subscription.php */