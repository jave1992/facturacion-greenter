<?php

namespace App\Services;

use DateTime;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
use Greenter\See;
use Greenter\Ws\Services\SunatEndpoints;
use Illuminate\Support\Facades\Storage;

class SunatService
{
    public function getSee($company)
    {
        $see = new See();
        $see->setCertificate(Storage::get($company->cert_path));
        $see->setService($company->production ? SunatEndpoints::FE_PRODUCCION : SunatEndpoints::FE_BETA);
        $see->setClaveSOL($company->ruc, $company->sol_user, $company->sol_pass);

        return $see;
    }

    public function getInvoice()
    {
        $invoice = (new Invoice())
            ->setUblVersion('2.1')
            ->setTipoOperacion('0101') // Venta - Catalog. 51
            ->setTipoDoc('01') // Factura - Catalog. 01 
            ->setSerie('F001')
            ->setCorrelativo('1')
            ->setFechaEmision(new DateTime()) // Zona horaria: Lima
            ->setFormaPago(new FormaPagoContado()) // FormaPago: Contado
            ->setTipoMoneda('PEN') // Sol - Catalog. 02
            ->setCompany($this->getCompany())
            ->setClient($this->getClient())
            ->setMtoOperGravadas(100.00)
            ->setMtoIGV(18.00)
            ->setTotalImpuestos(18.00)
            ->setValorVenta(100.00)
            ->setSubTotal(118.00)
            ->setMtoImpVenta(118.00)
            ->setDetails($this->getDetails())
            ->setLegends($this->getLegends());

        return $invoice;
    }

    public function getCompany()
    {
        $company = (new Company())
            ->setRuc('20123456789')
            ->setRazonSocial('GREEN SAC')
            ->setNombreComercial('GREEN')
            ->setAddress($this->getAddress());

        return $company;
    }

    public function getClient()
    {
        $client = (new Client())
            ->setTipoDoc('6') // RUC - Catalog. 06
            ->setNumDoc('20000000001')
            ->setRznSocial('EMPRESA X');

        return $client;
    }

    public function getAddress()
    {
        $address = (new Address())
            ->setUbigueo('150101')
            ->setDepartamento('LIMA')
            ->setProvincia('LIMA')
            ->setDistrito('LIMA')
            ->setUrbanizacion('-')
            ->setDireccion('Av. Villa Nueva 221')
            ->setCodLocal('0000'); // Codigo de establecimiento asignado por SUNAT, 0000 por defecto.

        return $address;
    }

    public function getDetails()
    {
        $item = (new SaleDetail())
            ->setCodProducto('P001')
            ->setUnidad('NIU') // Catalog. 03
            ->setDescripcion('PRODUCTO 1')
            ->setCantidad(1)
            ->setMtoValorUnitario(100.00)
            ->setMtoValorVenta(100.00)
            ->setMtoBaseIgv(100.00)
            ->setPorcentajeIgv(18.00) // 18%
            ->setIgv(18.00)
            ->setTipAfeIgv('10') // Gravado - Catalog. 07
            ->setTotalImpuestos(18.00)
            ->setMtoPrecioUnitario(118.00);

        return [$item];
    }

    public function getLegends()
    {
        $legend = (new Legend())
            ->setCode('1000') // Monto en letras - Catalog. 52
            ->setValue('SON: CIENTO DIECIOCHO CON 00/100 SOLES');

        return [$legend];
    }

    public function sunatResponse($result)
    {
        $response['success'] = $result->isSuccess();

        // Verificamos que la conexión con SUNAT fue exitosa.

        if (!$response['success']) {
            // Mostrar error al conectarse a SUNAT.

            $response['error'] = [
                'code' => $result->getError()->getCode(),
                'message' => $result->getError()->getMessage(),
            ];

            return $response;
        }

        $cdr = $result->getCdrResponse();

        $response['cdrResponse'] = [
            'code' => (int)$cdr->getCode(),
            'description' => $cdr->getDescription(),
            'notes' => $cdr->getNotes(),
        ];

        return $response;
    }
}
