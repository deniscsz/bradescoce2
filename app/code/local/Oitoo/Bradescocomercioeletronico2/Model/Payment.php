<?php
/**
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   payment
 * @package    Alex Braga_bradescoce
 * @copyright  Copyright (c) 2016 Kaisan (www.kaisan.com.br)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author     Alex Braga <>
 */

class Oitoo_Bradescocomercioeletronico2_Model_Payment extends Mage_Payment_Model_Method_Abstract
{

    protected $_code                        =       'bradescoce2';
    protected $_formBlockType               =       'bradescoce2/form';
    protected $_infoBlockType               =       'bradescoce2/info';
    protected $_canUseInternal              =       true;
    protected $_canUseForMultishipping      =       false;


    protected $_storeId                     =       '';
    protected $_chaveSeguranca              =       '';
    protected $_merchantId                  =       '';
    protected $_email                       =       '';




    public function confirmarPagamentos(){

        $this->_configmodulo        =   Mage::getSingleton('bradescoce2/payment');
        $this->_storeId             =   Mage::app()->getStore()->getId();
        $this->_chaveSeguranca      =   $this->_configmodulo->getConfigData('assinatura', $this->_storeId );
        $this->_merchantId          =   $this->_configmodulo->getConfigData('merchantid', $this->_storeId );
        $this->_email               =   $this->_configmodulo->getConfigData('email', $this->_storeId );
        $this->_ambienteProducao    =   $this->_configmodulo->getConfigData('ambiente', $this->_storeId );

        $tokenAutenticacao  = $this->setAutenticacao($this->_ambienteProducao, $this->_merchantId, $this->_chaveSeguranca , $this->_email );

        if($tokenAutenticacao){
            //autenticação realizada com sucesso
            $pedidosPagos = $this->getPedidosPagos($this->_ambienteProducao, $this->_merchantId, $tokenAutenticacao);

            if($pedidosPagos){

                foreach($pedidosPagos as $pedido){

                    //confere pagamento
                    if($pedido->status == "21" || $pedido->status == "23") {
                        //o boleto foi confirmado
                        $id_pedido = $pedido->numero;
                        //$this->criarFatura($id_pedido);
                        echo "Pedido pago: " . $id_pedido;
                    }

                }

            }

        }


    }

    public function criarFatura($pedidopago){
        $order = mage::getModel('sales/order')->loadByIncrementId($pedidopago);

        if($order->getState() != 'new') {
            Mage::log('Não foi possivel criar a fatura do boleto ' . $pedidopago . 'pois ela já existe!');
            return false;
        } else {
            try {
                //echo "Pedido pago encontrado";
                if(!$order->canInvoice())
                {
                    Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
                }
                $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
                if (!$invoice->getTotalQty()) {
                    Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
                }
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
                $invoice->register();
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
                $transactionSave->save();
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();
                Mage::log('A fatura do boleto ' . $pedidopago . ' foi criada com sucesso!');
                return true;
            }
            catch (Mage_Core_Exception $e) {
                Mage::log('Não foi possivel criar a fatura do boleto ' . $pedidopago . ':' . $e);
                return false;
            }
        }

    }

    public function getPedidosPagos($ambienteProducao, $merchantId, $token){

        $datainicial    =   date('Y/m/d h:m', strtotime("-3 day"));
        $datadehoje     =   date('Y/m/d h:m', strtotime(now()));


        if($ambienteProducao){
            $urlGetOrderList =
                "https://meiosdepagamentobradesco.com.br/SPSConsulta/GetOrderList/$merchantId/transferencia?token=$token&dataInicial=$datainicial&dataFinal=$datadehoje&status=1";
        } else {
            $urlGetOrderList =
                "https://homolog.meiosdepagamentobradesco.com.br/SPSConsulta/GetOrderList/$merchantId/transferencia?token=$token&dataInicial=$datainicial&dataFinal=$datadehoje&status=1";
        }



        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "Accept-Charset: UTF-8";
        $headers[] = "Accept-Encoding:  application/json";
        $headers[] = "Content-Type: application/json; charset=UTF-8";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlGetOrderList);
        curl_setopt($ch, CURLOPT_POST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);

        $result = json_decode($result);
        $success = ($result->status->codigo == 0);
        if($success){
            return $result->pedidos;
        } else {
            return false;
        }

    }



    public function setAutenticacao($ambienteProducao, $merchantId, $chaveSeguranca, $email) {

        if($ambienteProducao){
            $urlAutenticacao = 'https://meiosdepagamentobradesco.com.br/SPSConsulta/Authentication/' . $merchantId;
        } else {
            $urlAutenticacao = 'https://homolog.meiosdepagamentobradesco.com.br/SPSConsulta/Authentication/' . $merchantId;
        }

        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "Accept-Charset: UTF-8";
        $headers[] = "Accept-Encoding:  application/json";
        $headers[] = "Content-Type: application/json; charset=UTF-8";
        $AuthorizationHeader = $email.":".$chaveSeguranca;
        $AuthorizationHeaderBase64 = base64_encode($AuthorizationHeader);
        $headers[] = "Authorization: Basic ".$AuthorizationHeaderBase64;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlAutenticacao);
        curl_setopt($ch, CURLOPT_POST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);

        $result = json_decode($result);

        $success = ($result->status->codigo == 0);
        if($success){
            return $result->token->token;
        } else {
            return false;
        }
    }

    public function registrarBoleto($pedido, $cliente){

        $payment                    =   $pedido->getPayment();

        $this->_configmodulo        =   Mage::getSingleton('bradescoce2/payment');
        $this->_storeId             =   Mage::app()->getStore()->getId();
        $this->_chaveSeguranca      =   $this->_configmodulo->getConfigData('assinatura', $this->_storeId );
        $this->_merchantId          =   $this->_configmodulo->getConfigData('merchantid', $this->_storeId );
        $this->_email               =   $this->_configmodulo->getConfigData('email', $this->_storeId );
        $this->_ambienteProducao    =   $this->_configmodulo->getConfigData('ambiente', $this->_storeId );

        $configmodulo               =   $this->_configmodulo;

        if($this->_ambienteProducao){
            $url = 'https://meiosdepagamentobradesco.com.br/api';
        } else {
            $url = 'https://homolog.meiosdepagamentobradesco.com.br/api';
        }

        $cedente = $configmodulo->getConfigData('cedente', $this->_storeId);

        $valor = $pedido->getGrandTotal();
        $valor = str_replace(",", ".",$valor);
        $valor = str_replace("," , "", number_format($valor, 2, ",", "."));
        $valor = str_replace("." , "", $valor);

        $endereco       =   $pedido->getBillingAddress();
        $rua            =   $endereco->getStreet1();
        $numero         =   $endereco->getStreet2();
        $complemento    =   $endereco->getStreet3();
        $bairro         =   $endereco->getStreet4();

        if($complemento == NULL || $complemento == ''){
            $enderecoCompleto = $rua . ', ' . $numero . ', ' . $bairro;
        } else {
            $enderecoCompleto = $rua . ', ' . $numero . ', ' . $complemento . ', ' . $bairro;
        }

        $cep    = $endereco->getPostcode();
        $cep    = str_replace('-', '', $cep);

        $cidade = $endereco->getCity();
        $cidade = str_replace(")", "", $cidade);
        $cidade = str_replace("(", "", $cidade);

        $estado = $endereco->getRegionCode();

        $nome   = $endereco->getFirstname() . ' ' . $endereco->getLastname();
        $cgccpf = $cliente->getTaxvat();
        //it verify if cpf is empty
        if($cgccpf == '' || $cgccpf == NULL){
            $cgccpf = $pedido->getBillingAddress()->getVatId();
        }

        $cgccpf = str_replace(array('.','-',','),'',$cgccpf);


        $instrucao1     =   $configmodulo->getConfigData('instrucao1', $this->_storeId);
        $instrucao2     =   $configmodulo->getConfigData('instrucao2', $this->_storeId);
        $instrucao3     =   $configmodulo->getConfigData('instrucao3', $this->_storeId);
        $instrucao4     =   $configmodulo->getConfigData('instrucao4', $this->_storeId);
        $instrucao5     =   $configmodulo->getConfigData('instrucao5', $this->_storeId);
        $instrucao6     =   $configmodulo->getConfigData('instrucao6', $this->_storeId);
        $instrucao7     =   $configmodulo->getConfigData('instrucao7', $this->_storeId);
        $instrucao8     =   $configmodulo->getConfigData('instrucao8', $this->_storeId);
        $instrucao9     =   $configmodulo->getConfigData('instrucao9', $this->_storeId);
        $instrucao10    =   $configmodulo->getConfigData('instrucao10', $this->_storeId);
        $instrucao11    =   $configmodulo->getConfigData('instrucao11', $this->_storeId);
        $instrucao12    =   $configmodulo->getConfigData('instrucao12', $this->_storeId);

        $urlLogotipo    =   $configmodulo->getConfigData('urlLogotipo', $this->_storeId);
        $mensagemCabecalho = $configmodulo->getConfigData('mensagemCabecalho', $this->_storeId);

        $carteira       = $configmodulo->getConfigData('carteira', $this->_storeId);
        if($carteira == '') {
            $carteira = 25;
        }


        $BradescoDiasdeVencimento   =   $configmodulo->getConfigData('diasvencimento', $this->_storeId);
        $vencimento                 =   "86400" * $BradescoDiasdeVencimento + mktime(0,0,0,date('m'),date('d'),date('Y'));
        $vencimento                 =   date ("Y-m-d", $vencimento);

        $dataatual                  =   date("Y-m-d", strtotime(now()));


        //monta o array
        $data_service_pedido = array(
            "numero"    =>  $pedido->getIncrementId(),
            "valor"     =>  $valor,
            "descricao" =>  $_POST["pedido-descricao"]);
        $data_service_comprador_endereco = array(
            "cep"           =>  $cep,
            "logradouro"    =>  $rua,
            "numero"        =>  $numero,
            "complemento"   =>  $complemento,
            "bairro"        =>  $bairro,
            "cidade"        =>  $cidade,
            "uf"            =>  $estado);
        $data_service_comprador = array(
            "nome"          =>  $nome,
            "documento"     =>  $cgccpf,
            "endereco"      =>  $data_service_comprador_endereco,
            "ip"            =>  $_SERVER["REMOTE_ADDR"],
            "user_agent"    =>  $_SERVER["HTTP_USER_AGENT"]);
        $data_service_boleto_registro = null;
        $data_service_boleto_instrucoes = array(
            "instrucao_linha_1"     => $instrucao1,
            "instrucao_linha_2"     => $instrucao2,
            "instrucao_linha_3"     => $instrucao3,
            "instrucao_linha_4"     => $instrucao4,
            "instrucao_linha_5"     => $instrucao5,
            "instrucao_linha_6"     => $instrucao6,
            "instrucao_linha_7"     => $instrucao7,
            "instrucao_linha_8"     => $instrucao8,
            "instrucao_linha_9"     => $instrucao9,
            "instrucao_linha_10"    => $instrucao10,
            "instrucao_linha_11"    => $instrucao11,
            "instrucao_linha_12"    => $instrucao12);
        $data_service_boleto = array(
            "beneficiario"          => $cedente,
            "carteira"              => $carteira,
            "nosso_numero"          => $pedido->getIncrementId(), //igual ao número do pedido
            "data_emissao"          => $dataatual,
            "data_vencimento"       => $vencimento,
            "valor_titulo"          => $valor,
            "url_logotipo"          => $urlLogotipo,
            "mensagem_cabecalho"    => $mensagemCabecalho,
            "tipo_renderizacao"     => 0, //html
            "instrucoes"            => $data_service_boleto_instrucoes,
            "registro"              => $data_service_boleto_registro);
        $data_service_request = array(
            "merchant_id"       => $this->_merchantId,
            "meio_pagamento"    => "300",
            "pedido"            => $data_service_pedido,
            "comprador"         => $data_service_comprador,
            "boleto"            => $data_service_boleto,
            "token_request_confirmacao_pagamento" => $pedido->getEntityId());


        $data_post = json_encode($data_service_request);
        $url = $url . "/transacao";


        //Configuracao do cabecalho da requisicao
        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "Accept-Charset: UTF-8";
        $headers[] = "Accept-Encoding:  application/json";
        $headers[] = "Content-Type: application/json; charset=UTF-8";
        $AuthorizationHeader = $this->_merchantId.":". $this->_chaveSeguranca;
        $AuthorizationHeaderBase64 = base64_encode($AuthorizationHeader);
        $headers[] = "Authorization: Basic ".$AuthorizationHeaderBase64;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);

        $result = json_decode($result);
        if($result->status->codigo == 0){
            //sucesso - Salva dados do boleto
            $payment->setAdditionalInformation('token',$result->boleto->token);
            $payment->setAdditionalInformation('url_acesso',$result->boleto->url_acesso);
            $payment->setAdditionalInformation('linha_digitavel',$result->boleto->linha_digitavel);
            Mage::log('Boleto ' . $result->boleto->token . ' emitido com sucesso! Url de acesso: ' . $result->boleto->url_acesso);
            return true;
        } else {
            Mage::log('Ocorreu um erro ao emitir o boleto: ' . print_r($result));
            return false;
        }
        return $result;
    }





   public function getOrderPlaceRedirectUrl($orderId = 0)
	{
	   $params = array();
       $params['_secure'] = true;

	   if ($orderId != 0 && is_numeric($orderId)) {
	       $params['order_id'] = $orderId;
	   }


        return Mage::getUrl('bradescoce2/checkout/success', $params);
    }

}
