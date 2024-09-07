<?php

namespace App\Services;

use CURLFile;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Http;


class ZohoWorkdriveService
{
    private $url = "https://workdrive.zoho.com/api/v1/";
    private $urlwdrive = "https://www.zohoapis.com/workdrive/api/v1/";
    private $upload_url = "https://upload.zoho.com/workdrive-api/v1/";
    private $download_url = "https://download.zoho.com/v1/workdrive/download/";
    private $download_url2 = "https://download-accl.zoho.com/v1/workdrive/download/";





    private $zohoOAuthService;
    private $client;

    /**
     * ZohoWorkdriveService constructor.
     */
    public function __construct()
    {
        $this->zohoOAuthService = new ZohoOAuthService();
        $this->client = new Client();
    }

    /**
     * @param $url
     * @return string
     */
    private function urlApi($url)
    {
        return $this->url . $url;
    }

    /**
     * @return array
     */
    private function headers()
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Zoho-oauthtoken ' . $this->zohoOAuthService->getAccessToken()->access_token,
        ];
    }

    public function uploadFile($file_name, $folder_code, $file_path)
    {
        try {
            $full_path = $file_path;
            $cf = new CURLFile($full_path);

            $data = array(
                'file' => $cf,
            );
            $token = $this->zohoOAuthService->getAccessToken()->access_token;
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $this->upload_url . 'stream/upload',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Zoho-oauthtoken ' . $token,
                    'Content-type: multipart/form-data',
                    'x-filename: ' . $file_name,
                    'x-parent_id: ' . $folder_code,
                    'upload-id: ' . $file_path,
                    'x-streammode: 1'
                ),
            ));
            $response = curl_exec($curl);
            $response = json_decode($response, true);
            curl_close($curl);
            return $response;
        } catch (\Exception $e) {
            Log::error('error', ['message' => $e->getMessage()]);
            return [
                'status' => false,
                'message' => 'FAILURE',
                'error' => $e->getMessage()
            ];
        }
    }

    /*** leer el excel */
    public function downloadFile($file_name, $folder_code, $file_path)
    {
        try {
            $token = $this->zohoOAuthService->getAccessToken()->access_token;
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->url .'/files/'. $folder_code .'/files',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Zoho-oauthtoken ' . $token,
                ],
            ]);
            $response = curl_exec($curl);
            $response = json_decode($response, true);
            curl_close($curl);
             /*echo $element["attributes"]["download_url"];
                    echo $element["attributes"]["display_attr_name"];
                    echo $element["attributes"]["permalink"];
                    echo $element["attributes"]["type"];
                    echo $element["attributes"]["extn"];*/
                    // https://download.zoho.com/v1/workdrive/download/{resource_id}
                    //https://download-accl.zoho.com/v1/workdrive/download/uak3bf07f1e920fe549969ab240db3c0b601a


            foreach ($response['data'] as $element) {
                if ($element["attributes"]["display_attr_name"] == $file_name){
                    $durl=$element["attributes"]["download_url"];
                    $file_id = $element["id"];
                    try {
                            $curl_download = curl_init();
                            curl_setopt_array($curl_download, [
                                CURLOPT_URL => $durl,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_HTTPHEADER => [
                                    'Authorization: Zoho-oauthtoken ' . $token,
                                ],
                            ]);
                            $response_download = curl_exec($curl_download);
                            if (curl_errno($curl_download)) {
                                throw new \Exception(curl_error($curl_download));
                            }
                            //$file_path = $file_path."BIGCOMMERCE.xlsx";
                            $file_path = $file_path.$file_name;
                            curl_close($curl_download);
                            file_put_contents($file_path, $response_download);
                            return [
                                'status' => true,
                                'message' => 'File downloaded and saved successfully',
                                'file_path' => $file_path,
                            ];
                    } catch (\Exception $e) {
                        return [
                            'status' => false,
                            'message' => 'FAILURE',
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }

        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => 'FAILURE',
                'error' => $e->getMessage()
            ];
        }
    }

    public function downloadFilev2($file_name, $folder_code, $file_path)
    {
        try {
            $token = $this->zohoOAuthService->getAccessToken()->access_token;
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->url . '/files/' . $folder_code . '/files',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Zoho-oauthtoken ' . $token,
                ],
            ]);
            $response = curl_exec($curl);
            curl_close($curl);

            if (!$response) {
                throw new \Exception("No response from Zoho API.");
            }

            $response = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Error decoding JSON response: " . json_last_error_msg());
            }

            foreach ($response['data'] as $element) {
                if ($element["attributes"]["display_attr_name"] === $file_name) {
                    $durl = $element["attributes"]["download_url"];
                    $file_id = $element["id"];

                    try {
                        $curl_download = curl_init();
                        curl_setopt_array($curl_download, [
                            CURLOPT_URL => $durl,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_HTTPHEADER => [
                                'Authorization: Zoho-oauthtoken ' . $token,
                            ],
                        ]);

                        $response_download = curl_exec($curl_download);

                        if (curl_errno($curl_download)) {
                            throw new \Exception(curl_error($curl_download));
                        }

                        curl_close($curl_download);

                        if (!$response_download || strlen($response_download) < 1024) {
                            // Si el tamaño del archivo es menor a 1KB, probablemente está corrupto o vacío
                            throw new \Exception("El archivo descargado es demasiado pequeño o está vacío.");
                        }

                        $file_full_path = $file_path . $file_name;
                        file_put_contents($file_full_path, $response_download);

                        // Verifica el tamaño del archivo guardado en disco
                        if (file_exists($file_full_path) && filesize($file_full_path) < 1024) {
                            throw new \Exception("El archivo descargado es demasiado pequeño o está corrupto.");
                        }

                        return [
                            'status' => true,
                            'message' => 'File downloaded and saved successfully',
                            'file_path' => $file_full_path,
                        ];

                    } catch (\Exception $e) {
                        return [
                            'status' => false,
                            'message' => 'Error during file download',
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }

            // Si el archivo no se encuentra en la lista
            return [
                'status' => false,
                'message' => 'Archivo no encontrado en Zoho WorkDrive.',
            ];

        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => 'FAILURE',
                'error' => $e->getMessage(),
            ];
        }
    }


    public function createExternaLinks($resource_id, $link_name)
    {
        try {
            $data = [
                'data' => [
                    'attributes' => [
                        'resource_id' => $resource_id,
                        'link_name' => $link_name,
                        'request_user_data' => false,
                        'allow_download' => true,
                        'role_id' => 34
                    ],
                    'type' => 'links'
                ]
            ];

            $token = $this->zohoOAuthService->getAccessToken()->access_token;

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $this->url . 'links',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Zoho-oauthtoken ' . $token,
                    'Content-Type: application/json',
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);

            $response = json_decode($response);

            return $response;
        } catch (\Exception $e) {
            Log::error('createExternaLinks error', ['message' => $e->getMessage()]);
            return [
                'status' => false,
                'message' => 'FAILURE',
                'error' => $e->getMessage()
            ];
        }
    }

    //Insert data a CRM Zoho
    public function insertDataIntoZohoCRM($records)
    {
        $zohoApiUrl = 'https://www.zohoapis.com/crm/v6/bulk-write';
        $accessToken = $this->zohoOAuthService->getAccessToken()->access_token;
        $moduleAPIName = 'Products'; // Cambia esto según el módulo que estés usando

        // Preparar los datos para la carga masiva
        $jobData = [
            'operation' => 'insert',
            'resource' => [
                [
                    'type' => 'data',
                    'module' => $moduleAPIName,
                    'file' => [
                        'file_id' => $this->uploadCsvToZohoCRM($records),
                    ],
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
        ])->post($zohoApiUrl, $jobData);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Error al insertar los datos en Zoho CRM.');
    }

    public function uploadCsvToZohoCRM($records)
    {
        $fileName = 'tiny.csv';
        $relativeFilePath = 'public/input_file/' . $fileName; // Ruta relativa dentro de storage/app/public/
        $absoluteFilePath = storage_path('app/' . $relativeFilePath); // Ruta absoluta para acceder al archivo


        // Guardar los registros en un archivo CSV
        Excel::store(new \App\Exports\YourExportClass($records), $relativeFilePath);

        // Verificar si el archivo se ha guardado correctamente
        if (!Storage::exists($relativeFilePath)) {
            throw new \Exception('Error al guardar el archivo CSV.');
        }

        // Subir el archivo CSV a Zoho CRM
        $zohoFileUploadUrl = 'https://www.zohoapis.com/crm/v6/files';
        $accessToken = $this->zohoOAuthService->getAccessToken()->access_token;

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
        ])->attach(
            'file', file_get_contents($absoluteFilePath), $fileName
        )->post($zohoFileUploadUrl);

        if ($response->successful()) {
            return $response->json()['data'][0]['id'];
        }

        throw new \Exception('Error al subir el archivo CSV a Zoho CRM.');
    }

}
