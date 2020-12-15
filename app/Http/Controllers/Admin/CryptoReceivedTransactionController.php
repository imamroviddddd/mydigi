<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\Admin\CryptoReceivedTransactionsDataTable;
use App\Http\Controllers\Controller;
use App\Models\CryptoapiLog;
use App\Models\Transaction;
use App\Repositories\CryptoCurrencyRepository;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CryptoReceivedTransactionController extends Controller
{
    protected $transaction;
    /**
    * The CryptoCurrency repository instance.
    *
    * @var CryptoCurrencyRepository
    */
    protected $cryptoCurrency;

    public function __construct()
    {
        $this->transaction = new Transaction();
        $this->cryptoCurrency = new CryptoCurrencyRepository();
    }

    public function index(CryptoReceivedTransactionsDataTable $dataTable)
    {
        $data['menu']     = 'crypto-transactions';
        $data['sub_menu'] = 'crypto-received-transactions';

        $data['cryptoReceivedTransactionsCurrencies'] = $this->transaction->with('currency:id,code')->where('transaction_type_id', Crypto_Received)->groupBy('currency_id')->get(['currency_id']);
        if (isset($_GET['btn']))
        {
            $data['currency'] = $_GET['currency'];
            $data['user']     = $user     = $_GET['user_id'];
            $data['getName']  = $getName  = $this->transaction->getTransactionsUsersEndUsersName($user, Crypto_Received);
            if (empty($_GET['from']))
            {
                $data['from'] = null;
                $data['to']   = null;
            }
            else
            {
                $data['from'] = $_GET['from'];
                $data['to']   = $_GET['to'];
            }
        }
        else
        {
            $data['from']     = null;
            $data['to']       = null;
            $data['currency'] = 'all';
            $data['user']     = null;
        }
        return $dataTable->render('admin.crypto_transactions.received.index', $data);
    }

    public function cryptoReceivedTransactionsSearchUser(Request $request)
    {
        $search = $request->search;
        $user   = $this->transaction->getTransactionsUsersResponse($search, Crypto_Received);
        $res    = [
            'status' => 'fail',
        ];
        if (count($user) > 0)
        {
            $res = [
                'status' => 'success',
                'data'   => $user,
            ];
        }
        return json_encode($res);
    }

    public function cryptoReceivedTransactionsCsv()
    {
        $from     = !empty($_GET['startfrom']) ? setDateForDb($_GET['startfrom']) : null;
        $to       = !empty($_GET['endto']) ? setDateForDb($_GET['endto']) : null;
        $currency = isset($_GET['currency']) ? $_GET['currency'] : null;
        $user     = isset($_GET['user_id']) ? $_GET['user_id'] : null;

        $getCryptoReceivedTransactions = $this->transaction->getCryptoReceivedTransactions($from, $to, $currency, $user)->orderBy('transactions.id', 'desc')->get();
        // dd($getCryptoReceivedTransactions);

        $datas = [];
        if (!empty($getCryptoReceivedTransactions))
        {
            foreach ($getCryptoReceivedTransactions as $key => $value)
            {
                $datas[$key]['Date'] = dateFormat($value->created_at);

                $datas[$key]['Sender'] = !empty($value->end_user) ? $value->end_user->first_name . ' ' . $value->end_user->last_name : "-";

                $datas[$key]['Amount'] = '+' . $value->subtotal;

                $datas[$key]['Crypto Currency'] = $value->currency->code;

                $datas[$key]['Receiver'] = !empty($value->user) ? $value->user->first_name . ' ' . $value->user->last_name : "-";
            }
        }
        else
        {
            $datas[0]['Date']     = '';
            $datas[0]['Sender']   = '';
            $datas[0]['Amount']   = '';
            $datas[0]['Crypto Currency'] = '';
            $datas[0]['Receiver'] = '';
        }
        return Excel::create('crypto_received_transactions_list_' . time() . '', function ($excel) use ($datas)
        {
            $excel->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

            $excel->sheet('mySheet', function ($sheet) use ($datas)
            {
                $sheet->cells('A1:E1', function ($cells)
                {
                    $cells->setFontWeight('bold');
                });
                $sheet->fromArray($datas);
            });
        })->download();
    }

    public function cryptoReceivedTransactionsPdf()
    {
        $data['company_logo'] = getCompanyLogoWithoutSession();
        $from     = !empty($_GET['startfrom']) ? setDateForDb($_GET['startfrom']) : null;
        $to       = !empty($_GET['endto']) ? setDateForDb($_GET['endto']) : null;
        $currency = isset($_GET['currency']) ? $_GET['currency'] : null;
        $user     = isset($_GET['user_id']) ? $_GET['user_id'] : null;

        $data['getCryptoReceivedTransactions'] = $getCryptoReceivedTransactions = $this->transaction->getCryptoReceivedTransactions($from, $to, $currency, $user)->orderBy('transactions.id', 'desc')->get();
        if (isset($from) && isset($to))
        {
            $data['date_range'] = $_GET['startfrom'] . ' To ' . $_GET['endto'];
        }
        else
        {
            $data['date_range'] = 'N/A';
        }
        $mpdf = new \Mpdf\Mpdf(['tempDir' => __DIR__ . '/tmp']);
        $mpdf = new \Mpdf\Mpdf([
            'mode'        => 'utf-8',
            'format'      => 'A3',
            'orientation' => 'P',
        ]);
        $mpdf->autoScriptToLang         = true;
        $mpdf->autoLangToFont           = true;
        $mpdf->allow_charset_conversion = false;
        $mpdf->WriteHTML(view('admin.crypto_transactions.received.crypto_received_transactions_report_pdf', $data));
        $mpdf->Output('crypto_received_transactions_report_' . time() . '.pdf', 'D');
    }

    public function view($id)
    {
        $data['menu']     = 'crypto-transactions';
        $data['sub_menu'] = 'crypto-received-transactions';

        $data['transaction'] = $transaction = $this->transaction->with([
            'user:id,first_name,last_name',
            'end_user:id,first_name,last_name',
            'currency:id,code,symbol',
            'payment_method:id,name',
            'cryptoapi_log:id,object_id,payload,confirmations',
        ])
        ->where('transaction_type_id', Crypto_Received)
        ->exclude(['merchant_id', 'bank_id', 'file_id', 'refund_reference', 'transaction_reference_id', 'email', 'phone', 'note'])
        ->find($id);
        // dd($transaction);

        // Get crypto api log details for Crypto_Received
        if (!empty($transaction->cryptoapi_log))
        {
            $getCryptoDetails = $this->cryptoCurrency->getCryptoPayloadConfirmationsDetails($transaction->transaction_type_id, $transaction->cryptoapi_log->payload, $transaction->cryptoapi_log->confirmations);
            if (count($getCryptoDetails) > 0)
            {
                // For "Tracking block io account receiver address changes, if amount is sent from other payment gateways like CoinBase, CoinPayments, etc"
                if (isset($getCryptoDetails['senderAddress']))
                {
                    $data['senderAddress']   = $getCryptoDetails['senderAddress'];
                }
                if (isset($getCryptoDetails['receiverAddress']))
                {
                    $data['receiverAddress'] = $getCryptoDetails['receiverAddress'];
                }
                $data['txId']            = $getCryptoDetails['txId'];
                $data['confirmations']   = $getCryptoDetails['confirmations'];
            }
        }
        return view('admin.crypto_transactions.received.view', $data);
    }

}
