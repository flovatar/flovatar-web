<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDustSeriesRequest;
use App\Http\Requests\UpdateDustSeriesRequest;
use App\Models\DustSeries;

class DustSeriesController extends Controller
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
     * @param  \App\Http\Requests\StoreDustSeriesRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreDustSeriesRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\DustSeries  $dustSeries
     * @return \Illuminate\Http\Response
     */
    public function show(DustSeries $dustSeries)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\DustSeries  $dustSeries
     * @return \Illuminate\Http\Response
     */
    public function edit(DustSeries $dustSeries)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateDustSeriesRequest  $request
     * @param  \App\Models\DustSeries  $dustSeries
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateDustSeriesRequest $request, DustSeries $dustSeries)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\DustSeries  $dustSeries
     * @return \Illuminate\Http\Response
     */
    public function destroy(DustSeries $dustSeries)
    {
        //
    }
}
