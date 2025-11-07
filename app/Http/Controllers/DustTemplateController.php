<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDustTemplateRequest;
use App\Http\Requests\UpdateDustTemplateRequest;
use App\Models\DustTemplate;

class DustTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreDustTemplateRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreDustTemplateRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\DustTemplate  $dustTemplate
     * @return \Illuminate\Http\Response
     */
    public function show(DustTemplate $dustTemplate)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\DustTemplate  $dustTemplate
     * @return \Illuminate\Http\Response
     */
    public function edit(DustTemplate $dustTemplate)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateDustTemplateRequest  $request
     * @param  \App\Models\DustTemplate  $dustTemplate
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateDustTemplateRequest $request, DustTemplate $dustTemplate)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\DustTemplate  $dustTemplate
     * @return \Illuminate\Http\Response
     */
    public function destroy(DustTemplate $dustTemplate)
    {
        //
    }
}
