<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;

class ZohoCRMService
{
    private $url = "https://www.zohoapis.com/crm/v6/";
    private $zohoOAuthService;
    private $client;

    /**
     * ZohoCRMService constructor.
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

    /**
     * Get record by ID.
     *
     * @param $module
     * @param $id
     * @return array
     */
    public function getRecordById($module, $id)
    {
        $url = $this->urlApi("{$module}/{$id}");
        $response = $this->client->request('GET', $url, [
            'headers' => $this->headers(),
        ]);

        try {
            $decodedResponse = json_decode($response->getBody()->getContents(), true);
            return $decodedResponse['data'][0];
        } catch (\Exception $e) {
            Log::error('error', ['message' => $e->getMessage()]);
            return [
                'status' => false,
                'message' => 'FAILURE',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Search records.
     *
     * @param $module
     * @param $criteria
     * @return array
     */
    public function searchRecords($module, $criteria, $field = 'criteria')
    {
        try {
            $url = $this->urlApi("{$module}/search?{$field}={$criteria}");
            $response = $this->client->request('GET', $url, [
                'headers' => $this->headers(),
            ]);

            $decodedResponse = json_decode($response->getBody()->getContents(), true);
            return $decodedResponse;
        } catch (\Exception $e) {
            Log::error('error', ['message' => $e->getMessage()]);
            return [
                'status' => false,
                'message' => 'FAILURE',
                'error' => $e->getMessage()
            ];
        }
    }

    public function getRecords($module, $query = [])
    {
        $url = $this->urlApi("{$module}");
        $response = $this->client->request('GET', $url, [
            'headers' => $this->headers(),
            'query' => $query
        ]);

        try {
            $res = $response->getBody()->getContents();
            Log::info('response', ['message' => $res]);
            $decodedResponse = json_decode($res, true);
            return $decodedResponse;
        } catch (\Exception $e) {
            Log::error('error', ['message' => $e->getMessage()]);
            return [
                'status' => false,
                'message' => 'FAILURE',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create records.
     *
     * @param $module
     * @param $criteria
     * @return array
     */
    public function createRecords($module, $data, $trigger = [])
    {
        $url = $this->urlApi("{$module}");
        $response = $this->client->request('POST', $url, [
            'headers' => $this->headers(),
            'body' => json_encode([
                'data' => count(array_filter(array_keys($data), 'is_string')) > 0 ? [$data] : $data,
                'trigger' => $trigger
            ])
        ]);

        try {
            $decodedResponse = json_decode($response->getBody()->getContents(), true);
            return $decodedResponse;
        } catch (\Exception $e) {
            Log::error('error', ['message' => $e->getMessage()]);
            return [
                'status' => false,
                'message' => 'FAILURE',
                'error' => $e->getMessage()
            ];
        }
    }


    
    /**
     * @return array
     */
    private function headersUpload()
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Zoho-oauthtoken ' . $this->zohoOAuthService->getAccessToken()->access_token,
            'X-CRM-ORG' => '770767446',
            'feature' => 'bulk-write'
        ];
    }

    /**
     * Upload File CRM
     *
     * @param $module
     * @param $criteria
     * @return array
     */
    public function uploadFileCrm()
    {

        $url = 'https://content.zohoapis.com/crm/v6/upload';
        $header_crm = $this->headersUpload();
        $fileName = 'tiny2.zip';
        $relativeFilePath = 'public/touploadcrm/' . $fileName; // Ruta relativa dentro de storage/app/public/
        $absoluteFilePath = storage_path('app/' . $relativeFilePath); // Ruta absoluta para acceder al archivo      
        //$fields["file"] = fopen($absoluteFilePath, 'rb');
            try {
                $response =  $this->client->request('POST', $url, [
                    'headers' => $header_crm,
                    'multipart' => [
                        [
                            'name'     => 'file',
                            'contents' => fopen($absoluteFilePath, 'r'),
                            'filename' => $fileName,
                        ],
                    ],
                ]);
    
                    $responseBody = $response->getBody()->getContents();
                    $responseData = json_decode($responseBody, true);
                    if (isset($responseData['code']) && $responseData['code'] === 'FILE_UPLOAD_SUCCESS') {
                        $fileId = $responseData['details']['file_id'];
                        echo "File ID: " . $fileId;
                    } else {
                        echo "File upload failed.";
                    }
            } catch (\Exception $e) {      

                return response()->json(['error' => $e->getMessage()], 500);
            }
                
            try {
                
                $zohoApiUrl = 'https://www.zohoapis.com/crm/bulk/v6/write';
                $jobData = [
                    "operation" => "upsert",
                    "ignore_empty" => true,
                    "callBack" => [
                        "url" => "https://sandbox.zohoapis.com/crm/v2/functions/sa_bulk_write_callback/actions/execute?auth_type=apikey&zapikey=1003.cf4f41dc4abb4a1dc38d1486144923c5.ea4bbb3ba57cbc2357d018bf4f3dea28",
                        "method" => "post"
                    ],
                    "resource" => [
                        [
                            "type" => "data",
                            "module" => [
                                "api_name" => "Products"
                            ],
                            "find_by" => "ITEM_No",                           
                            "file_id" => $fileId,
                            "field_mappings" => [
                                [
                                    "api_name" => "ITEM_No",
                                    "index" => 0
                                ],
                                [
                                    "api_name" => "MFR_No",
                                    "index" => 1
                                ],
                                [
                                    "api_name" => "Unit_Of_Measure",
                                    "index" => 2
                                ],
                                [
                                    "api_name" => "Product_Name",
                                    "index" => 3
                                ],
                                [
                                    "api_name" => "Product_Description",
                                    "index" => 5
                                ],
                                [
                                    "api_name" => "Manufacturer_Name",
                                    "index" => 6
                                ],
                                [
                                    "api_name" => "bigcommerce_json",
                                    "index" => 26
                                ]
                            ]
                        ]
                    ]
                ];
                $header = $this->headers();       
                $accessToken = $this->zohoOAuthService->getAccessToken()->access_token;
                $response = $this->client->request('POST', $zohoApiUrl, [
                    'headers' => $header,
                    'json' => $jobData
                ]);
               
            $responseBodyInsert = $response->getBody()->getContents();
            $responseData = json_decode($responseBodyInsert, true);
            dd($responseData);
        } catch (\Exception $e) {
            // Handle exception            
            return response()->json(['error' => $e->getMessage()], 500);
        }
          
            
       

        
    }





}
