<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request as Req;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Company;
use App\Models\Year;
use App\Models\Entry;
use Inertia\Inertia;
use Carbon\Carbon;
use Exception;

class DocumentController extends Controller
{
    public function index()
    {
        return Inertia::render(
            'Documents/Index',
            [
                'data' => Document::all()
                    ->where('company_id', session('company_id'))
                    ->where('year_id', session('year_id'))
                    ->map(function ($document) {

                        $date = new Carbon($document->date);

                        return [
                            'id' => $document->id,
                            'ref' => $document->ref,
                            'date' => $date->format('M d, Y'),
                            'description' => $document->description,
                            'type_id' => $document->type_id,
                            'company_id' => session('company_id'),
                            'year_id' => session('year_id'),
                            'delete' => Entry::where('document_id', $document->id)->first() ? false : true,
                        ];
                    }),

                'companies' => Company::all()
                    ->map(function ($com) {
                        return [
                            'id' => $com->id,
                            'name' => $com->name,
                        ];
                    }),

                'years' => Year::all()
                    ->where('company_id', session('company_id'))
                    ->map(function ($year) {
                        $begin = new Carbon($year->begin);
                        $end = new Carbon($year->end);

                        return [
                            'id' => $year->id,
                            'name' => $begin->format('M d, Y') . ' - ' . $end->format('M d, Y'),
                        ];
                    }),
            ]
        );
    }

    public function create()
    {
        $accounts = \App\Models\Account::all()->map->only('id', 'name');
        $account_first = \App\Models\Account::all('id', 'name')->first();

        $doc_type_first = \App\Models\DocumentType::all('id', 'name')->first();

        if ($doc_type_first && $account_first) {
            return Inertia::render('Documents/Create', [
                'accounts' => $accounts, 'account_first' => $account_first,
                'doc_type_first' => $doc_type_first,
                'doc_types' => DocumentType::all()
                    ->map(function ($doc_type) {
                        return [
                            'id' => $doc_type->id,
                            'name' => $doc_type->name,
                            'ref' => $doc_type->prefix . "/" . $doc_type->timestamps,
                        ];
                    }),
            ]);
        } else {
            if ($doc_type_first) {
                return Redirect::route('accounts.create')->with('success', 'ACCOUNTS NOT FOUND, Please create an account first');
            } else {
                return Redirect::route('documenttypes.create')->with('success', 'VOUCHER NOT FOUND, Please create a voucher first');
            }
        }
    }


    public function store(Req $request)
    {
        Request::validate([
            'type_id' => ['required'],
            'description' => ['required'],
            'date' => ['required', 'date'],

            'entries.*.account_id' => ['required'],
            'entries.*.debit' => ['required'],
            'entries.*.credit' => ['required'],
        ]);

        DB::transaction(function () use ($request) {
            $date = new Carbon($request->date);
            try {

                $prefix = \App\Models\DocumentType::where('id', $request->type_id)->first()->prefix;
                $date = $date->format('Y-m-d');
                $ref_date_parts = explode("-", $date);
                $reference = $prefix . "/" . $ref_date_parts[0] . "/" . $ref_date_parts[1] . "/" . $ref_date_parts[2];

                $doc = Document::create([
                    'type_id' => Request::input('type_id'),
                    'company_id' => session('company_id'),
                    'description' => Request::input('description'),
                    'ref' => $reference,
                    'date' => $date,
                    'year_id' => session('year_id'),
                ]);

                $data = $request->entries;
                foreach ($data as $entry) {
                    Entry::create([
                        'company_id' => $doc->company_id,
                        'account_id' => $entry['account_id'],
                        'year_id' => $doc->year_id,
                        'document_id' => $doc->id,
                        'debit' => $entry['debit'],
                        'credit' => $entry['credit'],
                    ]);
                }
            } catch (Exception $e) {
                return $e;
            }
        });

        return Redirect::route('documents')->with('success', 'Transaction created.');
    }

    public function edit(Document $document)
    {
        $accounts = \App\Models\Account::all()->map->only('id', 'name');

        $doc_types = \App\Models\DocumentType::all()->map->only('id', 'name');
        // $doc_types = \App\Models\DocumentType::all()->map->only('id', 'name')->first();
        $doc = \App\Models\Document::all()->where('id', $document->id)->map->only('id', 'ref')->first();

        $ref = Entry::all()->where('document_id', $document->id);
        $entrie = Entry::all()->where('document_id', $document->id)->toArray();

        $i = 0;
        foreach ($entrie as $entry) {
            $entries[$i] = $entry;
            $i++;
        }
        // dd($entries);

        // $document =
        //     // $document,
        //     [
        //         'id' => $document->id,
        //         'ref' => $document->ref,
        //         'date' => $document->date,
        //         'description' => $document->description,
        //         'type_id' => $document->type_id,
        //         'entries' => $document->entries,
        //     ];
        //     'accounts' => $accounts,
        //     'doc_types' => $doc_types,
        //     'entries' => $entries,
        // ]
        // dd($document);
        // $document = Document::all()->where('document_id', $document->id);
        return Inertia::render(
            'Documents/Edit',
            [
                'document' =>
                // $document,
                [
                    'id' => $document->id,
                    'ref' => $document->ref,
                    'date' => $document->date,
                    'description' => $document->description,
                    'type_id' => $document->type_id,
                    'type_name' => $document->documentType->name,
                    'entries' => $document->entries,
                ],
                'accounts' => $accounts,
                'doc_types' => $doc_types,
                'entriess' => $entries,
            ]
        );
    }

    // public function update(Document $document)
    public function update(
        Req $request,
        Document $document
        // Entry $entry
    ) {
        // dd($request->entries);
        // dd($entry);
        // dd($document->id);

        Request::validate([
            'type_id' => ['required'],
            'description' => ['required'],
            'date' => ['required', 'date'],

            'entries.*.account_id' => ['required'],
            'entries.*.debit' => ['required'],
            'entries.*.credit' => ['required'],
        ]);

        $preEntries = Entry::all()->where('document_id', $document->id);


        DB::transaction(function () use ($request, $document, $preEntries) {
            $date = new Carbon($request->date);
            try {

                foreach ($preEntries as $preEntry) {
                    $preEntry->delete();
                }
                // $prefix = \App\Models\DocumentType::where('id', $request->type_id)->first()->prefix;
                // $date = $date->format('Y-m-d');
                // $ref_date_parts = explode("-", $date);
                // $reference = $prefix . "/" . $ref_date_parts[0] . "/" . $ref_date_parts[1] . "/" . $ref_date_parts[2];

                // $doc = Document::create([
                //     'type_id' => Request::input('type_id'),
                //     'company_id' => session('company_id'),
                //     'description' => Request::input('description'),
                //     'ref' => $reference,
                //     'date' => $date,
                //     'year_id' => session('year_id'),
                // ]);
                $date = new carbon(Request::input('date'));

                $document->description = Request::input('description');
                $document->date = $date->format('Y-m-d');

                $document->save();

                $data = $request->entries;
                foreach ($data as $entry) {
                    Entry::create([
                        // 'company_id' => $document->company_id,
                        'company_id' => session('company_id'),
                        'account_id' => $entry['account_id'],
                        'year_id' => session('year_id'),
                        // 'year_id' => $document->year_id,
                        'document_id' => $document->id,
                        'debit' => $entry['debit'],
                        'credit' => $entry['credit'],
                    ]);
                }
            } catch (Exception $e) {
                return $e;
            }
        });

        // dd($request->entries);
        // dd(Request::input('ref'));
        // dd($document->type_id);


        // $data = $request->entries;
        // foreach ($data as $entry) {
        // Entry::create([
        // 'company_id' => $doc->company_id,
        // 'year_id' => $doc->year_id,
        // 'document_id' => $doc->id,

        // $entry->account_id = $entry->account_id;
        // $entry->debit = $entry->debit;
        // $entry->credit = $entry->credit;

        // $entry->save;

        // 'account_id' => $entry['account_id'],
        // 'debit' => $entry['debit'],
        // 'credit' => $entry['credit'],
        // ]);
        // }

        return Redirect::route('documents')->with('success', 'Transaction updated.');
    }


    public function destroy(Document $document)
    {
        $document->delete();
        return Redirect::back()->with('success', 'Transaction deleted.');
    }
}
