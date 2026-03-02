{{--
    @file index.blade.php
    @description Vista principal del módulo de Utilería de Planeación.
    @relatedFiles finalizar-ordenes.blade.php, mover-ordenes.blade.php

    ! REPORTE DE FUNCIONALIDAD - Módulo Utilería
    * -----------------------------------------------
    * Este blade es el punto de entrada del módulo Utilería.
    * Presenta dos opciones principales:
    *   1. Finalizar Órdenes - Abre modal para seleccionar telar y finalizar órdenes en proceso
    *   2. Mover Órdenes - Abre modal con interfaz drag-and-drop para mover órdenes entre telares
    * Incluye los partials de cada funcionalidad como @include
    * -----------------------------------------------
--}}

@extends('layouts.app')

@section('page-title')
    <x-layout.page-title title="Utilería" />
@endsection

@section('content')
    <div class="container mx-auto px-4 py-8">
        {{-- * Tarjetas de opciones principales --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto">

            {{-- ? Opción 1: Finalizar Órdenes --}}
            <button
                type="button"
                onclick="abrirModalFinalizar()"
                class="group bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-8 text-left border border-gray-100 hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-400"
            >
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-16 h-16 bg-green-100 rounded-xl flex items-center justify-center group-hover:bg-green-200 transition-colors">
                        <i class="fas fa-check-double text-green-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-gray-800 group-hover:text-green-700 transition-colors">
                            Finalizar Órdenes
                        </h3>
                        <p class="text-sm text-gray-500 mt-1 leading-relaxed">
                            Selecciona un telar y finaliza las órdenes de producción.
                        </p>
                    </div>
                    <div class="flex-shrink-0 text-gray-300 group-hover:text-green-500 transition-colors mt-1">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </div>
            </button>

            {{-- ? Opción 2: Mover Órdenes --}}
            <button
                type="button"
                onclick="abrirModalMover()"
                class="group bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-8 text-left border border-gray-100 hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-400"
            >
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-16 h-16 bg-blue-100 rounded-xl flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                        <i class="fas fa-exchange-alt text-blue-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-gray-800 group-hover:text-blue-700 transition-colors">
                            Mover Órdenes
                        </h3>
                        <p class="text-sm text-gray-500 mt-1 leading-relaxed">
                            Transfiere registros de un telar a otro mediante una interfaz de arrastrar y soltar.
                        </p>
                    </div>
                    <div class="flex-shrink-0 text-gray-300 group-hover:text-blue-500 transition-colors mt-1">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </div>
            </button>
        </div>
    </div>

    {{-- * Incluir modales de cada funcionalidad --}}
    @include('planeacion.utileria.finalizar-ordenes')
    @include('planeacion.utileria.mover-ordenes')
@endsection
