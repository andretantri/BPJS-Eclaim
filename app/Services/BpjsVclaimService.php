<?php

namespace App\Services;

use App\Models\Claim;
use Illuminate\Support\Facades\Http;

class BpjsVclaimService
{
    protected string $baseUrl;

    protected ?string $consId;

    protected ?string $secretKey;

    protected ?string $userKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.bpjs.vclaim_url', 'https://apijkn-dev.bpjs-kesehatan.go.id/vclaim-rest-dev'), '/');
        $this->consId = config('services.bpjs.cons_id');
        $this->secretKey = config('services.bpjs.secret_key');
        $this->userKey = config('services.bpjs.user_key');
    }

    /**
     * Check if BPJS API credentials are configured.
     */
    public function isConfigured(): bool
    {
        return filled($this->consId) && filled($this->secretKey) && filled($this->userKey);
    }

    /**
     * Search SEP by number, or return an explicitly local simulation.
     *
     * @return array<string, mixed>
     */
    public function searchSep(string $noSep): array
    {
        if ($this->isConfigured()) {
            return $this->request("SEP/{$noSep}", 'GET');
        }

        $claim = Claim::with('patient')->where('no_sep', $noSep)->first();

        if ($claim === null) {
            return [
                'metaData' => [
                    'code' => '201',
                    'message' => 'SEP tidak ditemukan pada data lokal simulasi.',
                ],
                'response' => null,
                'simulated' => true,
            ];
        }

        return [
            'metaData' => [
                'code' => '200',
                'message' => 'Sukses (Simulasi Lokal)',
            ],
            'response' => [
                'noSep' => $noSep,
                'tglSep' => $claim->tanggal_masuk?->format('Y-m-d'),
                'noRujukan' => $claim->no_rujukan,
                'ppkPerujuk' => $claim->patient?->faskes_tingkat_pertama,
                'jnsPelayanan' => $claim->care_type,
                'diagnosa' => $claim->diagnosa_primer,
                'peserta' => [
                    'nama' => $claim->patient?->nama_lengkap,
                    'noKartu' => $claim->patient?->no_kis,
                    'noMr' => $claim->patient?->no_rm,
                    'jnsPeserta' => $claim->patient?->segmen_peserta,
                    'hakKelas' => $claim->patient?->kelas_hak,
                    'statusPeserta' => [
                        'keterangan' => 'Belum diverifikasi',
                    ],
                ],
                'is_match' => false,
                'catatan' => 'Data diambil dari database lokal aplikasi. Kepesertaan dan kecocokan SEP belum dikonfirmasi BPJS.',
            ],
            'simulated' => true,
        ];
    }

    /**
     * Verify participant eligibility.
     *
     * @return array<string, mixed>
     */
    public function verifyParticipant(string $noKartu): array
    {
        if ($this->isConfigured()) {
            $result = $this->request("Peserta/noKartu/{$noKartu}/TglSep/".now()->format('Y-m-d'), 'GET');
            $participant = data_get($result, 'response.peserta');

            if (! is_array($participant)) {
                return [
                    'status' => 'unknown',
                    'peserta_aktif' => false,
                    'hak_layanan_sesuai' => false,
                    'tidak_ada_sep_ganda' => false,
                    'verified_at' => now()->format('d M Y, H.i').' WIB',
                    'simulated' => false,
                    'error' => data_get($result, 'metaData.message', 'Respons BPJS tidak dapat diurai.'),
                ];
            }

            return [
                'status' => data_get($participant, 'statusPeserta.keterangan', 'unknown'),
                'peserta_aktif' => data_get($participant, 'statusPeserta.kode') === '0',
                'hak_layanan_sesuai' => (bool) data_get($participant, 'hakKelas'),
                'tidak_ada_sep_ganda' => true,
                'verified_at' => now()->format('d M Y, H.i').' WIB',
                'simulated' => false,
            ];
        }

        return [
            'status' => 'tidak_diverifikasi',
            'peserta_aktif' => false,
            'hak_layanan_sesuai' => false,
            'tidak_ada_sep_ganda' => false,
            'verified_at' => now()->format('d M Y, H.i').' WIB',
            'simulated' => true,
            'error' => 'Kredensial BPJS belum dikonfigurasi; status kepesertaan tidak diverifikasi.',
        ];
    }

    /**
     * Send a signed request to BPJS V-Claim API.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function request(string $endpoint, string $method = 'GET', array $data = []): array
    {
        $timestamp = (string) time();
        $signature = base64_encode(hash_hmac('sha256', $this->consId.'&'.$timestamp, $this->secretKey, true));

        $pendingRequest = Http::withHeaders([
            'X-cons-id' => $this->consId,
            'X-timestamp' => $timestamp,
            'X-signature' => $signature,
            'user_key' => $this->userKey,
        ])->connectTimeout(3)->timeout(10);

        if ($method === 'GET') {
            $pendingRequest = $pendingRequest->retry(2, 200);
        }

        try {
            $response = $method === 'GET'
                ? $pendingRequest->get("{$this->baseUrl}/{$endpoint}")
                : $pendingRequest->post("{$this->baseUrl}/{$endpoint}", $data);

            $payload = $response->json();

            if (! is_array($payload)) {
                return [
                    'metaData' => [
                        'code' => '502',
                        'message' => 'Respons BPJS tidak dapat diurai sebagai JSON. Periksa format enkripsi adapter.',
                    ],
                    'response' => null,
                    'simulated' => false,
                ];
            }

            return array_merge($payload, ['simulated' => false]);
        } catch (\Throwable $exception) {
            return [
                'metaData' => [
                    'code' => '500',
                    'message' => $exception->getMessage(),
                ],
                'response' => null,
                'simulated' => false,
            ];
        }
    }
}
