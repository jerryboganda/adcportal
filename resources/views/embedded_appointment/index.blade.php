<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Appointment</title>
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap-datepicker.min.css') }}">
    <style>
        @import url('https://fonts.googleapis.com/css?family=Roboto');

        body {
            font-family: 'Roboto', sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
        }

        i {
            margin-right: 10px;
        }

        /*------------------------*/
        input:focus,
        button:focus,
        .form-control:focus {
            outline: none;
            box-shadow: none;
        }

        .form-control:disabled,
        .form-control[readonly] {
            background-color: #fff;
        }

        /*----------step-wizard------------*/
        .d-flex {
            display: flex;
        }

        .justify-content-center {
            justify-content: center;
        }

        .align-items-center {
            align-items: center;
        }

        /*---------signup-step-------------*/
        .bg-color {
            background-color: #333;
        }

        .signup-step-container {
            padding: 150px 0px;
            padding-bottom: 60px;
            position: relative;
            background-image: linear-gradient(to top, rgb(40 167 69 / 23%) 0%, rgb(40 167 69 / 16%) 100%);
            height: 100vh;
            overflow: auto;
        }

        .signup-step-container .wizard .tab-pane {
            padding: 20px 10px;
        }

        .signup-step-container .wizard .tab-pane .card {
            box-shadow: rgba(0, 0, 0, 0.1) 0px 4px 12px;
        }

        .signup-step-container .wizard .section-title h1 {
            text-transform: capitalize;
            text-align: center;
            margin-bottom: 30px;
        }

        .input-group-text svg {
            height: 15px;
            width: 15px;
        }

        .wizard .nav-tabs {
            position: relative;
            margin-bottom: 0;
            border-bottom-color: transparent;
        }

        .wizard>div.wizard-inner {
            position: relative;
            margin-bottom: 30px;
            text-align: center;
        }

        .connecting-line {
            height: 2px;
            background: #e0e0e0;
            position: absolute;
            width: 75%;
            margin: 0 auto;
            left: 0;
            right: 0;
            top: 15px;
            z-index: 1;
        }

        .wizard .nav-tabs>li.active>a,
        .wizard .nav-tabs>li.active>a:hover,
        .wizard .nav-tabs>li.active>a:focus {
            color: #555555;
            cursor: default;
            border: 0;
            border-bottom-color: transparent;
        }

        .wizard .nav-tabs>li.progress-bar-success:not(:last-of-type) a::after {
            background: #0db02b;
        }

        span.round-tab {
            width: 30px;
            height: 30px;
            margin: 0 auto;
            line-height: 30px;
            display: inline-block;
            border-radius: 50%;
            background: #fff;
            z-index: 2;
            text-align: center;
            font-size: 16px;
            color: #0e214b;
            font-weight: 500;
            border: 1px solid #ddd;
            position: relative;
            box-shadow: rgba(0, 0, 0, 0.35) 0px 5px 15px;
        }

        span.round-tab i {
            color: #555555;
        }


        .wizard li.active span.round-tab {
            background: #0db02b;
            color: #fff;
            border-color: #0db02b;
        }

        .wizard li.active span.round-tab i {
            color: #5bc0de;
        }

        .wizard .nav-tabs>li.active>a i {
            color: #0db02b;
        }

        .wizard .nav-tabs>li {
            width: 25%;
        }

        .wizard li:after {
            content: " ";
            position: absolute;
            left: 46%;
            opacity: 0;
            margin: 0 auto;
            bottom: 0px;
            border: 5px solid transparent;
            border-bottom-color: red;
            transition: 0.1s ease-in-out;
        }



        .wizard .nav-tabs>li a {
            background-color: transparent;
            display: block;
            text-align: center;
            position: relative;
        }

        .wizard .nav-tabs>li:not(:last-of-type) a::after {
            position: absolute;
            content: "";
            left: 0;
            height: 2%;
            width: 100%;
            background: #878b86e0;
            top: 50%;
            left: 56%;

        }

        .appointment-date-tab .input-group input {
            padding: 10px;
            height: auto;
        }

        .wizard .nav-tabs>li a i {
            position: absolute;
            top: -15px;
            font-style: normal;
            font-weight: 400;
            white-space: nowrap;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 12px;
            font-weight: 700;
            color: #000;
        }

        .wizard .nav-tabs>li a:hover {
            background: transparent;
        }

        .wizard .tab-pane {
            position: relative;
            padding-top: 20px;
        }


        .wizard h3 {
            margin-top: 0;
        }

        .prev-step,
        .next-step {
            font-size: 14px;
            padding: 8px 24px;
            border: none;
            border-radius: 4px;
            margin-top: 30px;
        }

        .prev-step.btn-transparent {
            background-color: transparent;
            border: 1px solid #0db02b;
        }

        .next-step {
            background-color: #0db02b;
            color: #fff;
            font-size: 14px;
        }

        .submit-step {
            font-size: 14px;
            padding: 8px 24px;
            border: none;
            border-radius: 4px;
            margin-top: 30px;
        }

        .submit-step {
            background-color: #0db02b;
            color: #fff;
            font-size: 14px;
        }

        .step-head {
            font-size: 20px;
            text-align: center;
            font-weight: 500;
            margin-bottom: 20px;
        }

        .term-check {
            font-size: 14px;
            font-weight: 400;
        }

        .custom-file {
            position: relative;
            display: inline-block;
            width: 100%;
            height: 40px;
            margin-bottom: 0;
        }

        .custom-file-input {
            position: relative;
            z-index: 2;
            width: 100%;
            height: 40px;
            margin: 0;
            opacity: 0;
        }

        .custom-file-label {
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            z-index: 1;
            height: 40px;
            padding: .375rem .75rem;
            font-weight: 400;
            line-height: 2;
            color: #495057;
            background-color: #fff;
            border: 1px solid #ced4da;
            border-radius: .25rem;
        }

        .custom-file-label::after {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            z-index: 3;
            display: block;
            height: 38px;
            padding: .375rem .75rem;
            line-height: 2;
            color: #495057;
            content: "Browse";
            background-color: #e9ecef;
            border-left: inherit;
            border-radius: 0 .25rem .25rem 0;
        }

        .footer-link {
            margin-top: 30px;
        }

        .all-info-container {}

        .list-content {
            margin-bottom: 10px;
        }

        .list-content a {
            padding: 10px 15px;
            width: 100%;
            display: inline-block;
            background-color: #f5f5f5;
            position: relative;
            color: #565656;
            font-weight: 400;
            border-radius: 4px;
        }

        .list-content a[aria-expanded="true"] i {
            transform: rotate(180deg);
        }

        .list-content a i {
            text-align: right;
            position: absolute;
            top: 15px;
            right: 10px;
            transition: 0.5s;
        }

        .form-control[disabled],
        .form-control[readonly],
        fieldset[disabled] .form-control {
            background-color: #fdfdfd;
        }

        .list-box {
            padding: 10px;
        }

        .signup-logo-header .logo_area {
            width: 200px;
        }

        .signup-logo-header .nav>li {
            padding: 0;
        }

        .signup-logo-header .header-flex {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .list-inline li {
            display: inline-block;
        }

        .pull-right {
            float: right;
        }

        /*-----------custom-checkbox-----------*/
        /*----------Custom-Checkbox---------*/
        input[type="checkbox"] {
            position: relative;
            display: inline-block;
            margin-right: 5px;
        }

        input[type="checkbox"]::before,
        input[type="checkbox"]::after {
            position: absolute;
            content: "";
            display: inline-block;
        }

        input[type="checkbox"]::before {
            height: 16px;
            width: 16px;
            border: 1px solid #999;
            left: 0px;
            top: 0px;
            background-color: #fff;
            border-radius: 2px;
        }

        input[type="checkbox"]::after {
            height: 5px;
            width: 9px;
            left: 4px;
            top: 4px;
        }

        input[type="checkbox"]:checked::after {
            content: "";
            border-left: 1px solid #fff;
            border-bottom: 1px solid #fff;
            transform: rotate(-45deg);
        }

        input[type="checkbox"]:checked::before {
            background-color: #18ba60;
            border-color: #18ba60;
        }

        .service-card-wrapper ul {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .service-image {
            width: 60px;
            height: 60px;
            border: 1px solid var(--bs-card-border-color);
            border-radius: 50%;
            overflow: hidden;
        }

        .service-image img,
        .staff-image img {
            width: 100%;
            height: 100%;
        }

        .service-card-wrapper .service-card span.service-price {
            line-height: 1;
            background: transparent;
            padding: 0;
            margin: 0;
        }

        .service-card-wrapper .service-card .service-left-content {
            flex: 1;
        }

        .service-card-wrapper .service-card-left {
            width: 100%;
        }

        .service-card-wrapper .service-card .service-bottom-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .service-card-wrapper .service-card p svg {
            height: 14px;
            width: 14px;
            margin-right: 5px;
        }

        .service-card-wrapper .service-card input[type="radio"] {
            display: none;
            padding: 0;
            border: 0;
            background: transparent;
        }

        .service-card-wrapper .service-card input[type="radio"]:checked+span {
            background: #0db02bbd;
        }

        .service-card-wrapper .service-card input[type="radio"]:checked+span h6,
        .service-card-wrapper .service-card input[type="radio"]:checked+span .service-price,
        .service-card-wrapper .service-card input[type="radio"]:checked+span p {
            color: #ffffff;
        }

        .service-card-wrapper .service-card input[type="radio"]:checked+span p svg path {
            fill: #ffffff;
        }

        .service-card-wrapper .service-card span {
            width: auto;
            padding: 15px;
            background: rgb(40 167 69 / 7%);
            border-radius: 10px;
            font-size: 14px;
            height: 100%;
            display: block;
            cursor: pointer;
            position: relative;
            color: #000;
            margin: 0;
            vertical-align: bottom;
            border: 1px solid transparent;
        }

        .service-card-wrapper .service-card label {
            width: 100%;
            margin-bottom: 15px;
        }

        .service-card-wrapper span {
            cursor: pointer;
            position: relative;
            font-size: 12px;
            padding-left: 24px;
            color: #000;
            margin: 0;
            vertical-align: bottom;
            width: 100%;
        }

        .location-card .card-content-left span {
            width: 100%;
            padding-left: 0px;
            display: block;
            text-align: center;
            margin-top: 10px;
        }

        .service-card-wrapper ul li label {
            display: block;
        }

        .service-content-tab,
        .staff-content-tab {
            margin-bottom: 30px;
        }

        .service-card-wrapper h4 {
            font-weight: 500;
            color: #0db02b;
            font-size: 24px;
            text-transform: capitalize;
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 20px
        }

        .service-card-wrapper h4 svg path {
            fill: #0db02b;
        }

        .service-card-wrapper ul li input[type="radio"]:checked+span h6 {
            color: #fff
        }

        .service-card-wrapper ul li input[type="radio"]:checked+span .service-card-right svg path {
            fill: #fff
        }

        .service-card-wrapper ul li input[type="radio"]:checked+span .service-price {
            background: rgb(255 255 255 / 26%);
            color: #fff;
        }

        .service-card-wrapper ul li input[type="radio"]:checked+span .service-card-right p {
            color: #fff;
        }

        .service-card-wrapper h6 {
            font-weight: 400;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .service-card-right p {
            display: flex;
            align-items: center;
            gap: 5px;

        }

        .staff-content-tab input[type="radio"],
        .location-card input[type="radio"],
        .appointment-date-tab input[type="radio"] {
            display: none !important;
        }


        .staff-content-tab .checkbox-custom label .card-content-left {
            position: relative;
            display: inline-block;
            vertical-align: middle;
            cursor: pointer;
            font-size: 14px;
            line-height: 1.1;
            margin: 0;
            padding: 0px;
            width: 100%;
            border: 0px;
            backdrop-filter: blur(10px);
            background-color: transparent;
            border-radius: 10px;
            -moz-border-radius: 10px;
            -ms-border-radius: 10px;
            -o-border-radius: 10px;
            transition: .2s all ease-in-out;
            -webkit-transition: .2s all ease-in-out;
            -moz-transition: .2s all ease-in-out;
            -ms-transition: .2s all ease-in-out;
            -o-transition: .2s all ease-in-out;
            -webkit-border-radius: 10px;
            z-index: 1;
            margin-bottom: 10px;
        }

        .staff-content-tab .checkbox-custom {
            border: 1px solid #0db02b;
            border-radius: 10px;

            overflow: hidden;
        }

        .staff-content-tab .staff-card-content {
            padding: 20px;
        }

        .staff-content-tab .staff-card-content .card-content-left {
            margin-bottom: 0px !important;
        }

        .staff-content-tab input:checked+label .staff-card-content {
            background: rgb(40 167 69 / 7%);

        }

        .staff-content-tab input:checked+label .staff-image {
            outline: 1px solid #0db02b;
            outline-offset: 2px;
        }


        .staff-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            display: block;
            overflow: hidden;
            margin: 0 auto 15px;
        }

        .staff-card-content .card-content-left b {
            font-size: 16px;
            text-transform: capitalize;
            text-align: center;
            margin-bottom: 10px;
        }

        .staff-content-tab .row,
        .location-content-tab .row {
            row-gap: 20px;
        }

        .location-card .location-card-inner {
            background: rgb(40 167 69 / 21%);
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid transparent;
        }

        .location-content-tab input:checked+label .location-card-inner {
            border: 2px solid #0db02b;
        }

        .location-image {
            width: 100%;
            position: relative;
        }

        .location-image img {
            width: 100%;
            height: 100%;
        }

        .location-icon {
            background: #0db02b;
            border: 3px solid #fff;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            right: 0;
            left: 0;
            top: 50%;
            margin: 0 auto;
            transform: translateY(-50%);
        }

        .location-icon svg path {
            fill: #fff;
        }

        .location-card .location-card-inner .card-content-left {
            padding: 15px;
        }

        .location-card .location-card-inner .card-content-left p {
            text-align: center;
            font-size: 18px;
            font-weight: 500;
            display: block;
        }

        .appointment-date-tab .appoint-date-list input[type="radio"]:checked+span {
            border: 2px solid #0db02b;
        }

        .appointment-date-tab .appoint-date-list span {
            width: auto;
            padding: 10px;
            background-color: rgb(40 167 69 / 7%);
            border-radius: 10px;
            font-size: 14px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            position: relative;
            color: #000;
            margin: 0;
            vertical-align: bottom;
            text-align: center;
            list-style: 1;
            border: 2px solid transparent;
        }

        .appointment-date-tab .appoint-date-list span svg {
            margin-right: 7px;
            width: 17px;
            height: 17px;
        }

        .appoint-date-list {
            row-gap: 15px;
        }

        .btn-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 30px !important;
        }

        .btn-wrapper .default-btn {
            margin-top: 0;
        }

        .booking-input input {
            border: 1px solid transparent;
            background-color: rgb(40 167 69 / 7%);
            padding: 13px 15px;
            font-size: 14px;
            height: 100%;
        }

        .booking-input label {
            font-weight: 500;
        }

        .booking-info-tab .row {
            row-gap: 15px;
        }

        .booking-input .form-control:focus {
            border: 1px solid #0db02b;
            background-color: rgb(40 167 69 / 7%);
        }

        .booking-info-tab.booking-radio .checkbox-custom input {
            padding: 0;
            height: initial;
            width: initial;
            margin-bottom: 0;
            display: none;
            cursor: pointer;
        }

        .booking-info-tab.booking-radio .checkbox-custom label {
            position: relative;
            display: inline-block;
            vertical-align: middle;
            cursor: pointer;
            font-size: 14px;
            line-height: 1.1;
            margin: 0;
            padding: 12px 30px 12px 20px;
            width: 100%;
            border: 1px solid #e5e7eb;
            font-size: 14px;
            background-color: transparent;
            outline: none;
            line-height: 1.2;
            border-radius: 10px;
            -moz-border-radius: 10px;
            -ms-border-radius: 10px;
            -o-border-radius: 10px;
            transition: .2s all ease-in-out;
            -webkit-transition: .2s all ease-in-out;
            -moz-transition: .2s all ease-in-out;
            -ms-transition: .2s all ease-in-out;
            -o-transition: .2s all ease-in-out;
            -webkit-border-radius: 10px;
        }

        .booking-info-tab.booking-radio .checkbox-custom:not(:last-child) {
            margin-bottom: 15px;
        }

        .booking-info-tab.booking-radio .checkbox-custom label:before {
            content: '';
            appearance: none;
            -webkit-appearance: none;
            background-color: transparent;
            border: 1px solid #e5e7eb;
            padding: 8px;
            display: inline-block;
            position: absolute;
            vertical-align: middle;
            cursor: pointer;
            right: 10px;
            top: 50%;
            border-radius: 5px;
            transform: translateY(-50%);
            -webkit-transform: translateY(-50%);
            -moz-transform: translateY(-50%);
            -ms-transform: translateY(-50%);
            -o-transform: translateY(-50%);
            -webkit-border-radius: 5px;
            -moz-border-radius: 5px;
            -ms-border-radius: 5px;
            -o-border-radius: 5px;
            margin-left: 15px;
        }

        .booking-info-tab.booking-radio .checkbox-custom input:checked+label:after {
            content: '';
            display: block;
            position: absolute;
            top: 50%;
            right: 17px;
            width: 4px;
            height: 8px;
            border: 1px solid #0db02b;
            border-width: 0px 1px 1px 0;
            transform: translateY(-50%) rotate(45deg);
            -moz-transform: translateY(-50%) rotate(45deg);
            -ms-transform: translateY(-50%) rotate(45deg);
            -o-transform: translateY(-50%) rotate(45deg);
            -webkit-transform: translateY(-50%) rotate(45deg);
        }

        .booking-info-tab.booking-radio .checkbox-custom span {
            display: inline-block;
            background-color: #d5d8db;
            padding: 5px 8px;
            border-radius: 50%;
            height: 30px;
            width: 30px;
            -webkit-border-radius: 50%;
            -moz-border-radius: 50%;
            -ms-border-radius: 50%;
            -o-border-radius: 50%;
            margin-right: 5px;
            transition: .2s all ease-in-out;
            -webkit-transition: .2s all ease-in-out;
            -moz-transition: .2s all ease-in-out;
            -ms-transition: .2s all ease-in-out;
            -o-transition: .2s all ease-in-out;
        }

        .booking-info-tab.booking-radio input:checked+label {
            background-color: rgb(40 167 69 / 7%);
            border: 1px solid #0db02b;
        }

        .booking-info-tab .nav-pills .tab-link .nav-link {
            background: rgb(40 167 69 / 7%);
            color: #000;
            border-radius: 10px;
            display: flex;
            align-items: center;
        }

        .booking-info-tab .nav-pills .nav-link svg {
            width: 17px;
            height: 17px;
            margin-right: 10px;
        }

        .booking-info-tab .nav-pills {
            justify-content: center;
            gap: 15px;
        }

        .booking-info-tab input:-internal-autofill-selected {
            background-color: rgb(40 167 69 / 7%) !important;
            border-color: #0db02b;
        }

        .booking-info-tab .nav-pills .tab-link.active .nav-link {
            background: #0db02b;
            color: #fff;
        }

        .booking-info-tab .nav-pills .tab-link.active .nav-link svg path {
            fill: #fff;
        }

        .wizard-inner .nav-tabs {
            justify-content: center;
            padding-top: 10px
        }

        .wizard-inner .nav-tabs p {
            margin-bottom: 0px;
            margin-top: 10px;
        }

        .booking-info-tab .tab-wrapper {
            margin-bottom: 30px;
        }

        .booking-info-tab .tab-content.active {
            display: block;
        }

        .booking-info-tab .tab-content {
            display: none;
        }

        .booking-error {
            padding: 8px;
            background: #ff03031f;
            border-radius: 10px;
            width: auto;
            display: flex;
            align-items: center;
            max-width: 50%;
            width: 100%;
            margin-bottom: 15px;
            justify-content: center;
        }

        .booking-error .error {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .booking-error svg {
            width: 14px;
            height: 14px;
        }

        .booking-error svg path {
            fill: #d50000;
        }

        .booking-error span {
            flex: 1;
        }

        .choose-files .form-control {
            height: auto;
        }

        @media (max-width: 1399px) {
            .location-icon {
                width: 40px;
                height: 40px;
            }

            .location-icon svg {
                width: 20px;
                height: 20px;
            }

            .wizard-inner .nav-tabs p {
                display: none;
            }
        }

        @media (max-width: 1199px) {
            .staff-image {
                width: 130px;
                height: 130px;
            }

        }

        @media (max-width: 767px) {
            .sign-content h3 {
                font-size: 40px;
            }

            .wizard .nav-tabs>li a i {
                display: none;
            }

            .signup-logo-header .navbar-toggle {
                margin: 0;
                margin-top: 8px;
            }

            .signup-logo-header .logo_area {
                margin-top: 0;
            }

            .signup-logo-header .header-flex {
                display: block;
            }

            .staff-card-content .card-content-left b {
                font-size: 14px;
            }

            .staff-image {
                width: 120px;
                height: 120px;
            }

            .service-content-tab,
            .staff-content-tab {
                margin-bottom: 20px;
            }

            .booking-error {
                max-width: 100%;
            }

            .wizard>div.wizard-inner {
                position: relative;
                margin-bottom: 20px;
            }

        }

        @media (max-width: 480px) {
            .service-card-inner {
                flex-direction: column;
                align-items: flex-start !important;
                row-gap: 15px;
            }

            .service-image {
                width: 50px;
                height: 50px;
            }

        }

        @media (max-width: 575px) {
            .signup-step-container .card-body {
                padding: 15px;
            }
        }
    </style>

    {{-- custom-css --}}
    <style type="text/css">
        {!! preg_replace('/<\/?(script|style)[^>]*>/i', '', htmlspecialchars_decode($customCss)) !!}
    </style>
</head>

<body>
    <section class="signup-step-container">

        <div class="container">
            <div class="row d-flex justify-content-center">
                <div class="col-md-8">
                    <div class="wizard">
                        <div class="section-title">
                            <h1>{{ __('Take Your Appointment') }} </h1>
                        </div>
                        <div class="wizard-inner">
                            <ul class="nav nav-tabs" role="tablist">
                                <li role="presentation" class="active">
                                    <a href="#step1" data-toggle="tab" aria-controls="step1" role="tab"
                                        aria-expanded="true"><span class="round-tab">{{ __('1') }} </span>
                                    </a>
                                    <p>{{ __('Choose Your Service') }}</p>
                                </li>
                                <li role="presentation" class="disabled">
                                    <a href="#step2" data-toggle="tab" aria-controls="step2" role="tab"
                                        aria-expanded="false">
                                        <span class="round-tab">{{ __('2') }}</span>

                                    </a>
                                    <p>{{ __('Pick a Time') }}</p>
                                </li>
                                <li role="presentation" class="disabled">
                                    <a href="#step3" data-toggle="tab" aria-controls="step3" role="tab"><span
                                            class="round-tab">{{ __('3') }}</span> </a>
                                    <p>{{ __('Share Your Details') }}</p>
                                </li>
                            </ul>
                        </div>

                        {{ Form::open(['url' => '#', 'method' => 'post', 'id' => 'appointment-form', 'enctype' => 'multipart/form-data']) }}
                        @csrf
                        <div class="tab-content" id="main_form">
                            <div class="tab-pane active" role="tabpanel" id="step1">
                                <div class="card">
                                    <div class="card-body service-card-wrapper">
                                        <div class="service-content-tab">
                                            <h4>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30"
                                                    viewBox="0 0 512 512" fill="none">
                                                    <g clip-path="url(#clip0_87_71)">
                                                        <path
                                                            d="M133.333 367.428L104.661 373.811C89.4626 377.109 75.8582 385.528 66.1258 397.659C56.3935 409.79 51.1241 424.896 51.2 440.448V454.255C51.2132 463.608 54.2922 472.7 59.9655 480.136C65.6388 487.573 73.5936 492.944 82.6112 495.428C110.302 502.98 162.859 512 256 512C349.141 512 401.698 502.98 429.389 495.428C438.407 492.944 446.361 487.573 452.035 480.136C457.708 472.7 460.787 463.608 460.8 454.255V440.448C460.876 424.896 455.607 409.79 445.874 397.659C436.142 385.528 422.537 377.109 407.339 373.811L378.667 367.428L339.55 343.97L323.823 307.277L328.943 301.38L332.792 302.413C336.818 303.478 340.965 304.02 345.131 304.026C357.714 303.998 369.772 298.98 378.659 290.073C387.546 281.165 392.536 269.095 392.533 256.512V246.272C399.842 244.385 406.319 240.129 410.951 234.169C415.583 228.209 418.109 220.882 418.133 213.333V179.2C418.105 171.655 415.578 164.331 410.946 158.375C406.314 152.419 399.839 148.165 392.533 146.278V136.533C392.533 100.322 378.149 65.5947 352.544 39.9897C326.939 14.3847 292.211 0 256 0C219.789 0 185.061 14.3847 159.456 39.9897C133.851 65.5947 119.467 100.322 119.467 136.533V146.278C112.161 148.165 105.686 152.419 101.054 158.375C96.4226 164.331 93.895 171.655 93.8667 179.2V213.333C93.8667 222.386 97.4629 231.068 103.864 237.469C110.265 243.87 118.947 247.467 128 247.467H145.067C147.98 247.438 150.867 246.907 153.6 245.897V248.841C153.595 261.023 158.017 272.793 166.042 281.958L188.228 307.26L172.493 343.953L133.333 367.428ZM176.777 361.276C192.211 381.613 213.345 396.901 237.491 405.197C229.263 413.255 222.426 422.62 217.259 432.913C190.396 420.374 166.972 401.512 148.992 377.941L176.777 361.276ZM443.733 440.448V454.255C443.737 459.874 441.895 465.339 438.491 469.81C435.087 474.281 430.309 477.51 424.892 479.002C398.208 486.246 347.247 494.933 256 494.933C164.753 494.933 113.792 486.246 87.1083 478.959C81.6978 477.469 76.9251 474.246 73.5219 469.784C70.1186 465.322 68.2726 459.867 68.2667 454.255V440.448C68.2082 428.783 72.1611 417.451 79.4629 408.354C86.7647 399.256 96.9718 392.944 108.373 390.477L132.898 385.024C142.515 397.935 171.674 433.374 212.958 449.57C216.49 450.928 220.406 450.896 223.915 449.48C227.425 448.064 230.267 445.37 231.868 441.941C237.466 430.097 245.725 419.706 256 411.58C266.279 419.704 274.543 430.091 280.149 441.933C281.359 444.509 283.275 446.688 285.676 448.215C288.077 449.743 290.863 450.556 293.709 450.56C295.543 450.562 297.361 450.218 299.068 449.545C340.344 433.331 369.502 397.909 379.119 384.998L403.644 390.451C415.047 392.923 425.253 399.24 432.552 408.343C439.851 417.445 443.799 428.781 443.733 440.448ZM294.741 432.913C289.578 422.618 282.741 413.252 274.509 405.197C298.655 396.901 319.789 381.613 335.224 361.276L363.008 377.941C345.026 401.51 321.603 420.372 294.741 432.913ZM363.605 280.619C357.415 285.332 349.644 287.472 341.914 286.592L345.958 281.967C353.983 272.797 358.404 261.025 358.4 248.841V245.897C361.133 246.907 364.02 247.438 366.933 247.467H375.467V256.512C375.483 261.173 374.421 265.774 372.364 269.957C370.306 274.139 367.308 277.788 363.605 280.619ZM341.333 144.939C262.938 142.583 246.963 108.015 246.861 107.767C246.228 106.183 245.134 104.824 243.721 103.867C242.308 102.91 240.64 102.399 238.933 102.4C214.106 102.439 190.221 111.914 172.117 128.905C179.755 86.5536 216.303 51.2 256 51.2C278.624 51.2248 300.315 60.2233 316.312 76.221C332.31 92.2187 341.309 113.909 341.333 136.533V144.939ZM401.067 179.2V213.333C401.067 217.86 399.269 222.201 396.068 225.401C392.867 228.602 388.526 230.4 384 230.4H366.933C364.67 230.4 362.5 229.501 360.899 227.901C359.299 226.3 358.4 224.13 358.4 221.867V170.667C358.4 168.403 359.299 166.233 360.899 164.633C362.5 163.032 364.67 162.133 366.933 162.133H384C388.526 162.133 392.867 163.931 396.068 167.132C399.269 170.333 401.067 174.674 401.067 179.2ZM256 17.0667C287.674 17.1005 318.041 29.6981 340.438 52.0951C362.835 74.4921 375.433 104.859 375.467 136.533V145.067H366.933C364.02 145.095 361.133 145.626 358.4 146.637V136.533C358.371 109.384 347.573 83.3554 328.375 64.158C309.178 44.9607 283.149 34.1627 256 34.1333C201.446 34.1333 153.6 85.9733 153.6 145.067V146.637C150.867 145.626 147.98 145.095 145.067 145.067H136.533V136.533C136.567 104.859 149.165 74.4921 171.562 52.0951C193.959 29.6981 224.326 17.1005 256 17.0667ZM145.067 230.4H128C123.474 230.4 119.133 228.602 115.932 225.401C112.731 222.201 110.933 217.86 110.933 213.333V179.2C110.933 174.674 112.731 170.333 115.932 167.132C119.133 163.931 123.474 162.133 128 162.133H145.067C147.33 162.133 149.5 163.032 151.101 164.633C152.701 166.233 153.6 168.403 153.6 170.667V221.867C153.6 224.13 152.701 226.3 151.101 227.901C149.5 229.501 147.33 230.4 145.067 230.4ZM170.667 248.841V156.16C177.86 145.75 187.28 137.071 198.243 130.752C209.207 124.434 221.439 120.634 234.052 119.629C242.108 131.823 268.629 159.863 341.333 161.946V248.841C341.335 256.892 338.411 264.671 333.107 270.729L323.123 282.138L299.23 275.755L299.486 274.697C300.008 272.516 300.095 270.254 299.742 268.039C299.39 265.825 298.604 263.701 297.43 261.791C296.257 259.88 294.718 258.219 292.902 256.904C291.086 255.588 289.029 254.643 286.848 254.123L261.956 248.149C255.35 246.567 248.386 247.675 242.596 251.227C236.806 254.78 232.665 260.487 231.083 267.093C229.501 273.7 230.608 280.664 234.161 286.454C237.713 292.243 243.42 296.385 250.027 297.967L274.918 303.94C276.232 304.255 277.578 304.416 278.929 304.418C282.57 304.42 286.115 303.247 289.036 301.073C291.957 298.899 294.099 295.841 295.142 292.352L310.579 296.482L297.813 311.04C294.181 315.194 289.702 318.523 284.678 320.804C279.653 323.085 274.199 324.266 268.681 324.267H243.32C237.802 324.263 232.348 323.082 227.324 320.8C222.3 318.519 217.821 315.192 214.187 311.04L178.91 270.72C173.6 264.668 170.67 256.892 170.667 248.841ZM282.88 270.729L278.921 287.317L254.02 281.344C252.917 281.096 251.875 280.632 250.953 279.978C250.031 279.323 249.248 278.493 248.65 277.533C248.052 276.574 247.65 275.506 247.468 274.39C247.287 273.274 247.328 272.133 247.591 271.034C247.854 269.935 248.333 268.898 248.999 267.985C249.666 267.072 250.508 266.301 251.475 265.716C252.442 265.131 253.516 264.744 254.634 264.578C255.752 264.411 256.893 264.469 257.988 264.747L282.88 270.729ZM201.318 322.278C206.556 328.266 213.013 333.064 220.257 336.351C227.501 339.637 235.365 341.336 243.32 341.333H268.681C276.635 341.336 284.499 339.636 291.743 336.35C298.987 333.063 305.444 328.266 310.682 322.278L311.364 321.502L323.115 348.937C306.925 371.266 282.997 386.763 256 392.405C229.003 386.763 205.075 371.266 188.885 348.937L200.644 321.502L201.318 322.278Z"
                                                            fill="black" />
                                                    </g>
                                                    <defs>
                                                        <clipPath id="clip0_87_71">
                                                            <rect width="512" height="512" fill="white" />
                                                        </clipPath>
                                                    </defs>
                                                </svg>
                                                {{ __('service') }}
                                            </h4>
                                            <div class="row">
                                                @foreach ($services as $key => $service)
                                                    <div class="col-lg-6 col-12 service-card">
                                                        <label>
                                                            <input type="radio" name="service"
                                                                value="{{ $service->id }}"
                                                                id="radio{{ $key }}" class="service" required>
                                                            <span>
                                                                <div
                                                                    class="service-card-inner d-flex align-items-center justify-content-between">
                                                                    <div
                                                                        class="service-card-left d-flex align-items-center gap-3">
                                                                        <div class="service-image">
                                                                            <img src="{{ check_file($service->image) ? get_file($service->image) : get_file('uploads/default/avatar.png') }}"
                                                                                alt="">
                                                                        </div>
                                                                        <div class="service-left-content">
                                                                            <h6> {{ $service->name }}</h6>
                                                                            <div class="service-bottom-wrapper">
                                                                                <span class="service-price">
                                                                                    $ {{ $service->price }}
                                                                                </span>
                                                                                <p class="mb-0">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg"
                                                                                        width="20" height="20"
                                                                                        viewBox="0 0 512 512"
                                                                                        fill="none">
                                                                                        <path
                                                                                            d="M256 499.2C121.899 499.2 12.8 390.101 12.8 256C12.8 121.899 121.899 12.8 256 12.8C390.101 12.8 499.2 121.899 499.2 256C499.2 390.101 390.101 499.2 256 499.2ZM256 35.962C134.671 35.962 35.962 134.671 35.962 256C35.962 377.329 134.671 476.038 256 476.038C377.329 476.038 476.038 377.329 476.038 256C476.038 134.671 377.329 35.962 256 35.962Z"
                                                                                            fill="black" />
                                                                                        <path
                                                                                            d="M369.236 267.581H256C249.604 267.581 244.419 262.396 244.419 256V90.0062C244.419 83.6101 249.604 78.4253 256 78.4253C262.396 78.4253 267.581 83.6101 267.581 90.0062V244.419H369.236C375.632 244.419 380.817 249.604 380.817 256C380.817 262.396 375.632 267.581 369.236 267.581Z"
                                                                                            fill="black" />
                                                                                    </svg>
                                                                                    {{ $service->duration }}
                                                                                    {{ __('Minute') }}
                                                                                </p>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </span>
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="staff-content-tab">
                                            <h4><svg xmlns="http://www.w3.org/2000/svg" width="30" height="30"
                                                    viewBox="0 0 512 512" fill="none">
                                                    <path
                                                        d="M511.147 477.824C508.48 462.485 484.288 389.611 483.157 386.261C478.357 373.227 471.957 359.872 457.6 350.315C438.059 337.344 417.515 328.427 400 321.429C390.677 317.696 379.947 313.643 369.664 309.888C375.936 300.096 380.693 289.344 384.043 280.256C393.408 277.333 401.387 265.067 404.096 248.043C405.461 239.509 405.248 231.083 403.413 224.341C401.28 216.405 397.525 212.416 394.069 210.432C393.301 201.963 391.765 193.749 389.461 185.899C390.805 181.141 391.595 175.467 391.765 168.661C392.405 144.555 368.875 97.1307 309.397 91.3493C305.792 91.008 302.123 91.3067 298.475 91.392C293.867 66.0267 269.909 29.6107 217.749 24.5547C193.643 22.208 167.893 29.8667 151.851 43.9893C143.808 51.0933 138.965 59.1787 137.771 67.5413C133.632 68.6933 128.64 71.808 124.096 79.4453C119.979 86.4213 117.013 95.7227 115.584 106.389C113.835 119.253 114.539 132.949 117.248 142.635C113.749 144.597 109.909 148.565 107.755 156.587C105.963 163.285 105.707 171.648 107.093 180.139C109.973 198.059 118.677 210.709 128.725 212.587C131.947 221.419 136.704 231.403 142.229 240.832C132.309 244.523 121.856 248.619 111.189 252.885C93.824 259.84 73.408 268.672 54.016 281.557C40.7253 290.389 34.2613 302.187 28.544 317.525C27.52 320.597 3.52001 392.917 0.874678 408.149C0.426678 410.624 1.13068 413.184 2.73068 415.104C4.35201 417.024 6.74134 418.155 9.28001 418.155H108.501C101.205 440.896 92.4587 468.992 90.9013 477.867C90.4533 480.341 91.136 482.901 92.7573 484.843C94.3787 486.763 96.768 487.893 99.3067 487.893H502.784C505.301 487.893 507.691 486.784 509.333 484.843C510.891 482.859 511.573 480.299 511.147 477.824ZM307.733 108.352C356.053 113.045 375.211 149.675 374.741 168.213C374.549 174.443 373.803 179.349 372.523 182.827C371.861 184.597 371.819 186.581 372.395 188.395C375.296 197.525 377.003 207.296 377.472 217.451C377.579 219.84 377.792 221.845 379.627 223.36C381.461 224.875 382.891 225.323 385.323 225.067C386.496 226.133 389.248 233.216 387.307 245.355C385.344 257.472 380.501 263.339 380.373 263.936C375.851 262.635 371.285 265.024 369.835 269.376C364.288 285.803 357.312 299.349 349.611 308.587C349.568 308.651 349.547 308.715 349.504 308.779C349.461 308.821 349.419 308.843 349.376 308.885C338.261 323.52 320.704 331.925 301.056 331.925C281.621 331.925 264.107 323.52 252.949 308.907C245.973 299.52 239.147 285.248 233.664 268.736C232.832 266.24 231.936 264.149 229.461 263.275C226.987 262.4 225.28 262.571 223.061 263.979C221.611 263.339 216.768 257.472 214.805 245.355C213.141 234.923 214.955 228.224 216.213 225.877C218.304 226.219 220.48 225.792 222.315 224.597C224.597 223.104 226.048 220.587 226.176 217.856L226.496 213.056C226.624 211.584 226.368 210.112 225.771 208.768C223.189 203.115 221.568 190.059 223.467 176.021C225.707 159.317 231.637 151.829 232.064 150.997C234.837 153.387 238.805 153.792 241.984 151.936C245.141 150.101 246.784 146.453 246.037 142.869C245.739 141.355 245.589 139.947 245.632 138.56C245.781 133.845 248.875 128.576 254.357 123.733C264.789 114.517 285.419 106.24 307.733 108.352ZM138.432 195.115C135.957 194.24 134.293 194.411 132.053 195.861C130.624 195.2 125.845 189.397 123.925 177.429C122.261 167.125 124.032 160.512 125.291 158.165C127.424 158.528 129.6 158.059 131.413 156.843C133.696 155.328 135.125 152.789 135.211 150.059L135.509 145.301C135.637 143.872 135.36 142.443 134.784 141.12C132.224 135.509 130.624 122.56 132.523 108.672C134.72 92.16 140.565 84.7147 140.885 83.84C143.659 86.2933 147.627 86.656 150.805 84.8213C154.005 82.9867 155.648 79.3173 154.901 75.7333C154.603 74.3253 154.475 72.896 154.517 71.4667C154.667 66.816 157.717 61.6107 163.136 56.8107C173.504 47.6587 194.005 39.4667 216.064 41.5573C259.2 45.7387 277.611 75.4987 281.493 93.3333C266.795 96.32 252.992 102.165 243.029 110.955C234.901 118.101 230.037 126.251 228.821 134.677C224.661 135.808 219.627 138.944 215.061 146.624C210.923 153.643 207.936 163.029 206.507 173.76C204.736 186.965 205.419 200.576 208.192 210.325C204.672 212.288 200.789 216.277 198.635 224.363C196.8 231.104 196.565 239.531 197.952 248.064C198.827 253.483 200.341 258.197 202.155 262.507C185.877 260.651 171.371 253.099 161.771 240.469C154.859 231.125 148.075 216.981 142.635 200.64C141.781 198.123 140.928 195.989 138.432 195.115ZM20.1173 401.045C25.8133 380.587 39.0827 339.84 44.608 323.179C49.3013 310.613 53.888 302.101 63.4453 295.744C81.536 283.733 100.928 275.349 119.147 268.053C129.749 263.808 141.547 259.157 152.405 255.189C166.699 271.019 186.944 280.341 209.6 280.341C212.437 280.341 215.232 280.149 217.963 279.872C218.56 280.064 219.093 280.555 219.691 280.661C222.955 289.6 227.755 299.712 233.344 309.248C223.147 313.045 212.459 317.248 202.005 321.429C184.533 328.427 164.011 337.301 144.405 350.315C131.029 359.211 124.501 371.093 118.763 386.517C118.485 387.328 116.629 392.96 113.984 401.045H20.1173ZM110.165 470.763C115.904 450.155 129.28 409.003 134.869 392.192C139.605 379.52 144.213 370.923 153.877 364.544C172.16 352.405 191.659 343.957 209.451 336.853C220.288 332.523 232.405 327.723 243.605 323.648C258.005 339.605 278.4 348.992 301.205 348.992C323.712 348.992 343.979 339.712 358.336 323.947C369.877 328.085 382.827 332.949 393.664 337.301C410.368 343.957 429.909 352.427 448.149 364.544C457.856 371.008 462.784 380.245 467.072 391.915C472.747 408.981 486.165 450.197 491.883 470.784H110.165V470.763Z"
                                                        fill="#050505" />
                                                </svg>
                                                {{ __('staff') }}
                                            </h4>
                                            <div class="row">
                                                @foreach ($staffs as $k => $staff)
                                                    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">
                                                        <div class="checkbox-custom">
                                                            <input type="radio" id="quiz-{{ $k }}"
                                                                name="staff" class="staff" required=""
                                                                value="{{ $staff->user->id }}">
                                                            <label for="quiz-{{ $k }}"
                                                                class="form-field d-block mb-0">
                                                                <div class="staff-card-content">
                                                                    <div class="staff-image">
                                                                        <img src="{{ check_file($staff->user->avatar) ? get_file($staff->user->avatar) : get_file('uploads/default/avatar.png') }}"
                                                                            alt="">
                                                                    </div>
                                                                    <div class="card-content-left">
                                                                        <b class=" d-block"> {{ $staff->name }}</b>
                                                                        <p class="text-center mb-0">
                                                                            {{ $staff->user->email }}</p>
                                                                    </div>

                                                                </div>
                                                            </label>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="location-content-tab">
                                            <h4>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30"
                                                    viewBox="0 0 512 512" fill="none">
                                                    <g clip-path="url(#clip0_86_43)">
                                                        <path
                                                            d="M256.98 40.0049C251.45 39.9649 246.94 44.4149 246.9 49.9349C246.86 55.4549 251.3 59.9649 256.83 60.0049C262.35 60.0449 266.86 55.6049 266.9 50.0849C266.94 44.5649 262.5 40.0549 256.98 40.0049Z"
                                                            fill="black" />
                                                        <path
                                                            d="M256.525 100.004C217.914 99.73 186.293 130.879 186.001 169.478C185.71 208.075 216.876 239.711 255.475 240.002C255.654 240.003 255.832 240.004 256.011 240.004C294.364 240.004 325.709 208.948 325.999 170.528C326.29 131.933 295.125 100.295 256.525 100.004ZM256.009 220.005C255.883 220.005 255.751 220.004 255.625 220.003C228.054 219.795 205.792 197.197 206 169.628C206.207 142.183 228.595 120.001 255.991 120.001C256.117 120.001 256.249 120.002 256.375 120.003C283.946 120.211 306.208 142.809 306 170.378C305.792 197.823 283.405 220.005 256.009 220.005Z"
                                                            fill="black" />
                                                        <path
                                                            d="M299.631 47.5889C294.429 45.7429 288.71 48.4679 286.864 53.6739C285.019 58.8799 287.744 64.5949 292.949 66.4409C336.996 82.0519 366.351 124.003 365.999 170.83C365.958 176.352 370.401 180.863 375.924 180.905C375.949 180.905 375.975 180.905 376.001 180.905C381.487 180.905 385.957 176.477 385.999 170.98C386.415 115.633 351.706 66.0459 299.631 47.5889Z"
                                                            fill="black" />
                                                        <path
                                                            d="M317.357 376.442C383.87 290.827 425.437 246.182 425.998 171.278C426.702 77.035 350.22 0 255.984 0C162.848 0 86.7101 75.428 86.0021 168.728C85.4301 245.663 127.769 290.247 194.741 376.428C128.116 386.384 86.0021 411.401 86.0021 442C86.0021 462.497 104.948 480.89 139.351 493.79C170.664 505.532 212.091 511.999 256 511.999C299.909 511.999 341.336 505.532 372.649 493.79C407.052 480.889 425.998 462.496 425.998 441.999C425.998 411.417 383.923 386.406 317.357 376.442ZM106.001 168.879C106.625 86.55 173.8 20 255.986 20C339.145 20 406.619 87.988 405.999 171.129C405.467 242.263 361.385 286.1 291.008 377.843C278.455 394.199 266.927 409.663 256.015 424.79C245.135 409.654 233.837 394.467 221.096 377.837C147.81 282.253 105.459 241.729 106.001 168.879ZM256 492C170.149 492 106.001 465.603 106.001 442C106.001 424.496 144.349 402.384 208.827 394.727C223.08 413.428 235.576 430.418 247.832 447.77C249.704 450.42 252.746 451.998 255.991 452C255.994 452 255.997 452 256 452C259.242 452 262.283 450.428 264.158 447.783C276.298 430.657 289.136 413.248 303.267 394.738C367.685 402.403 405.999 424.508 405.999 442.001C405.998 465.603 341.851 492 256 492Z"
                                                            fill="black" />
                                                    </g>
                                                    <defs>
                                                        <clipPath id="clip0_86_43">
                                                            <rect width="511.999" height="511.999" fill="white" />
                                                        </clipPath>
                                                    </defs>
                                                </svg>
                                                {{ __('location') }}
                                            </h4>
                                            <div class="row">
                                                @foreach ($locations as $ke => $location)
                                                    <div class="col-lg-4 col-md-6 col-sm-6 col-12">
                                                        <div class="location-card">
                                                            <input type="radio" id="location-{{ $ke }}"
                                                                name="location" class="location" required=""
                                                                value="{{ $location->id }}">
                                                            <label for="location-{{ $ke }}"
                                                                class="form-field d-block mb-0">
                                                                <div class="location-card-inner">
                                                                    <div class="location-image">
                                                                        <img src="{{ check_file($location->image) ? get_file($location->image) : get_file('uploads/default/avatar.png') }}"
                                                                            alt="">
                                                                        <div class="location-icon">
                                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                                width="30" height="30"
                                                                                viewBox="0 0 512 512" fill="none">
                                                                                <g clip-path="url(#clip0_86_43)">
                                                                                    <path
                                                                                        d="M256.98 40.0049C251.45 39.9649 246.94 44.4149 246.9 49.9349C246.86 55.4549 251.3 59.9649 256.83 60.0049C262.35 60.0449 266.86 55.6049 266.9 50.0849C266.94 44.5649 262.5 40.0549 256.98 40.0049Z"
                                                                                        fill="black" />
                                                                                    <path
                                                                                        d="M256.525 100.004C217.914 99.73 186.293 130.879 186.001 169.478C185.71 208.075 216.876 239.711 255.475 240.002C255.654 240.003 255.832 240.004 256.011 240.004C294.364 240.004 325.709 208.948 325.999 170.528C326.29 131.933 295.125 100.295 256.525 100.004ZM256.009 220.005C255.883 220.005 255.751 220.004 255.625 220.003C228.054 219.795 205.792 197.197 206 169.628C206.207 142.183 228.595 120.001 255.991 120.001C256.117 120.001 256.249 120.002 256.375 120.003C283.946 120.211 306.208 142.809 306 170.378C305.792 197.823 283.405 220.005 256.009 220.005Z"
                                                                                        fill="black" />
                                                                                    <path
                                                                                        d="M299.631 47.5889C294.429 45.7429 288.71 48.4679 286.864 53.6739C285.019 58.8799 287.744 64.5949 292.949 66.4409C336.996 82.0519 366.351 124.003 365.999 170.83C365.958 176.352 370.401 180.863 375.924 180.905C375.949 180.905 375.975 180.905 376.001 180.905C381.487 180.905 385.957 176.477 385.999 170.98C386.415 115.633 351.706 66.0459 299.631 47.5889Z"
                                                                                        fill="black" />
                                                                                    <path
                                                                                        d="M317.357 376.442C383.87 290.827 425.437 246.182 425.998 171.278C426.702 77.035 350.22 0 255.984 0C162.848 0 86.7101 75.428 86.0021 168.728C85.4301 245.663 127.769 290.247 194.741 376.428C128.116 386.384 86.0021 411.401 86.0021 442C86.0021 462.497 104.948 480.89 139.351 493.79C170.664 505.532 212.091 511.999 256 511.999C299.909 511.999 341.336 505.532 372.649 493.79C407.052 480.889 425.998 462.496 425.998 441.999C425.998 411.417 383.923 386.406 317.357 376.442ZM106.001 168.879C106.625 86.55 173.8 20 255.986 20C339.145 20 406.619 87.988 405.999 171.129C405.467 242.263 361.385 286.1 291.008 377.843C278.455 394.199 266.927 409.663 256.015 424.79C245.135 409.654 233.837 394.467 221.096 377.837C147.81 282.253 105.459 241.729 106.001 168.879ZM256 492C170.149 492 106.001 465.603 106.001 442C106.001 424.496 144.349 402.384 208.827 394.727C223.08 413.428 235.576 430.418 247.832 447.77C249.704 450.42 252.746 451.998 255.991 452C255.994 452 255.997 452 256 452C259.242 452 262.283 450.428 264.158 447.783C276.298 430.657 289.136 413.248 303.267 394.738C367.685 402.403 405.999 424.508 405.999 442.001C405.998 465.603 341.851 492 256 492Z"
                                                                                        fill="black" />
                                                                                </g>
                                                                                <defs>
                                                                                    <clipPath id="clip0_86_43">
                                                                                        <rect width="511.999"
                                                                                            height="511.999"
                                                                                            fill="white" />
                                                                                    </clipPath>
                                                                                </defs>
                                                                            </svg>
                                                                        </div>
                                                                    </div>
                                                                    <div class="card-content-left">
                                                                        <p class="mb-0"> {{ $location->name }}</p>
                                                                        <span class="mb-0">
                                                                            {{ $location->phone }}</span>
                                                                    </div>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <ul class="list-inline pull-right ">
                                    <li><button type="button"
                                            class="default-btn next-step">{{ __('Continue to next step') }}</button>
                                    </li>
                                </ul>
                            </div>

                            <div class="tab-pane" role="tabpanel" id="step2">
                                <div class="card">
                                    <div class="card-body appointment-date-tab">
                                        <div class="form-group ">
                                            <label for="appointment_date"
                                                class="col-form-label">{{ __('Appointment Date') }}</label>
                                            <div class="input-group date ">
                                                <input class="form-control datepicker" type="text" id="datepicker"
                                                    name="appointment_date" autocomplete="off" required="required"
                                                    value="{{ \Carbon\Carbon::today()->format('d-m-Y') }}">
                                                <span class="input-group-text">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20"
                                                        height="20" viewBox="0 0 512 512" fill="none">
                                                        <path
                                                            d="M256 499.2C121.899 499.2 12.8 390.101 12.8 256C12.8 121.899 121.899 12.8 256 12.8C390.101 12.8 499.2 121.899 499.2 256C499.2 390.101 390.101 499.2 256 499.2ZM256 35.962C134.671 35.962 35.962 134.671 35.962 256C35.962 377.329 134.671 476.038 256 476.038C377.329 476.038 476.038 377.329 476.038 256C476.038 134.671 377.329 35.962 256 35.962Z"
                                                            fill="black"></path>
                                                        <path
                                                            d="M369.236 267.581H256C249.604 267.581 244.419 262.396 244.419 256V90.0062C244.419 83.6101 249.604 78.4253 256 78.4253C262.396 78.4253 267.581 83.6101 267.581 90.0062V244.419H369.236C375.632 244.419 380.817 249.604 380.817 256C380.817 262.396 375.632 267.581 369.236 267.581Z"
                                                            fill="black"></path>
                                                    </svg>
                                                </span>
                                            </div>
                                        </div>
                                        <div id="timeSlotsContainer"></div>

                                        @if ($custom_field == 'on')
                                            <hr>
                                            <div class="mt-3">
                                                <div class="row align-items-center">
                                                    @foreach ($custom_fields as $custom_field)
                                                        <div class="col-sm-6 col-12 mb-3 form-group">
                                                            <label for={{ $custom_field->label }}
                                                                class="col-form-label">{{ $custom_field->label }}</label>
                                                            @if ($custom_field->type === 'textfield')
                                                                <input type="text"
                                                                    name="values[{{ $custom_field->label }}]"
                                                                    placeholder="Value"
                                                                    value="{{ $custom_field->value }}"
                                                                    class="custom_lbl form-control">
                                                            @elseif($custom_field->type === 'textarea')
                                                                <textarea name="values[{{ $custom_field->label }}]" placeholder="Value" class="custom_lbl form-control">{{ $custom_field->value }}</textarea>
                                                            @elseif($custom_field->type === 'date')
                                                                <input type="date" class="form-control custom_lbl"
                                                                    name="values[{{ $custom_field->label }}]"
                                                                    value="{{ $custom_field->value }}">
                                                            @elseif($custom_field->type === 'number')
                                                                <input type="number" class="form-control custom_lbl"
                                                                    name="values[{{ $custom_field->label }}]"
                                                                    placeholder="Value"
                                                                    value="{{ $custom_field->value }}">
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        @if (!empty($files) && $files->value == 'on')
                                            <hr>
                                            <div class="mt-2">
                                                <div class="form-group">
                                                    {{ Form::label('attachment', $files->label, ['class' => 'form-label']) }}
                                                    <div class="choose-files ">
                                                        <label for="attachment">
                                                            <input type="file" class="form-control file"
                                                                name="attachment" id="attachment"
                                                                data-filename="attachment">
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <ul class="list-inline pull-right btn-wrapper">
                                    <li><button type="button"
                                            class="default-btn btn-transparent prev-step">{{ __('Back') }}</button>
                                    </li>
                                    <li><button type="button"
                                            class="default-btn next-step">{{ __('Continue') }}</button></li>
                                </ul>
                            </div>

                            <div class="tab-pane" role="tabpanel" id="step3">
                                <div class="card">
                                    <div class="card-body booking-info-tab tabs-wrapper">
                                        <ul class="nav nav-pills nav-fill tab-wrapper tabs">
                                            <li class="nav-iteam tab-link active" data-tab="new-user">
                                                <a href="javascript:;" class="nav-link " id="new-user">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                                                        <path
                                                            d="M96 128a128 128 0 1 1 256 0A128 128 0 1 1 96 128zM0 482.3C0 383.8 79.8 304 178.3 304h91.4C368.2 304 448 383.8 448 482.3c0 16.4-13.3 29.7-29.7 29.7H29.7C13.3 512 0 498.7 0 482.3zM504 312V248H440c-13.3 0-24-10.7-24-24s10.7-24 24-24h64V136c0-13.3 10.7-24 24-24s24 10.7 24 24v64h64c13.3 0 24 10.7 24 24s-10.7 24-24 24H552v64c0 13.3-10.7 24-24 24s-24-10.7-24-24z" />
                                                    </svg>
                                                    {{ __('New Registration') }}</a>
                                            </li>
                                            <li class="nav-iteam tab-link " data-tab="existing-user">
                                                <a href="javascript:;" class="nav-link " id="existing-user">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                                                        <path
                                                            d="M96 128a128 128 0 1 1 256 0A128 128 0 1 1 96 128zM0 482.3C0 383.8 79.8 304 178.3 304h91.4C368.2 304 448 383.8 448 482.3c0 16.4-13.3 29.7-29.7 29.7H29.7C13.3 512 0 498.7 0 482.3zM625 177L497 305c-9.4 9.4-24.6 9.4-33.9 0l-64-64c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0l47 47L591 143c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9z" />
                                                    </svg>
                                                    {{ __('Already have account?') }}</a>
                                            </li>
                                            <li class="nav-iteam tab-link "data-tab="guest-user">
                                                <a href="javascript:;" class="nav-link " id="guest-user">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                                                        <path
                                                            d="M224 16c-6.7 0-10.8-2.8-15.5-6.1C201.9 5.4 194 0 176 0c-30.5 0-52 43.7-66 89.4C62.7 98.1 32 112.2 32 128c0 14.3 25 27.1 64.6 35.9c-.4 4-.6 8-.6 12.1c0 17 3.3 33.2 9.3 48H45.4C38 224 32 230 32 237.4c0 1.7 .3 3.4 1 5l38.8 96.9C28.2 371.8 0 423.8 0 482.3C0 498.7 13.3 512 29.7 512H418.3c16.4 0 29.7-13.3 29.7-29.7c0-58.5-28.2-110.4-71.7-143L415 242.4c.6-1.6 1-3.3 1-5c0-7.4-6-13.4-13.4-13.4H342.7c6-14.8 9.3-31 9.3-48c0-4.1-.2-8.1-.6-12.1C391 155.1 416 142.3 416 128c0-15.8-30.7-29.9-78-38.6C324 43.7 302.5 0 272 0c-18 0-25.9 5.4-32.5 9.9c-4.8 3.3-8.8 6.1-15.5 6.1zm56 208H267.6c-16.5 0-31.1-10.6-36.3-26.2c-2.3-7-12.2-7-14.5 0c-5.2 15.6-19.9 26.2-36.3 26.2H168c-22.1 0-40-17.9-40-40V169.6c28.2 4.1 61 6.4 96 6.4s67.8-2.3 96-6.4V184c0 22.1-17.9 40-40 40zm-88 96l16 32L176 480 128 288l64 32zm128-32L272 480 240 352l16-32 64-32z" />
                                                    </svg>
                                                    {{ __('Guest Booking') }}</a>
                                            </li>
                                        </ul>
                                        <input type="hidden" name="type" id="selected_tab" value="new-user">

                                        <div class="booking-error d-none">
                                            <div class="error">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                                                    <path
                                                        d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c9.4-9.4 24.6-9.4 33.9 0l47 47 47-47c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9l-47 47 47 47c9.4 9.4 9.4 24.6 0 33.9s-24.6 9.4-33.9 0l-47-47-47 47c-9.4 9.4-24.6 9.4-33.9 0s-9.4-24.6 0-33.9l47-47-47-47c-9.4-9.4-9.4-24.6 0-33.9z" />
                                                </svg>
                                                <span class="error-msg"> </span>
                                            </div>
                                        </div>
                                        <div class="tabs-container">
                                            <div class="tab-content active" id="new-user">
                                                <div class="row row-gap-3">
                                                    <div class="col-md-6 col-12">
                                                        <div class="form-group booking-input mb-0">
                                                            <label>{{ __('Name') }} *</label>
                                                            <input class="form-control" type="text" name="name"
                                                                class="name" id="name"
                                                                placeholder="Your Full Name">
                                                        </div>
                                                    </div>
                                                    <div class=" col-md-6 col-12">
                                                        <div class="form-group booking-input mb-0">
                                                            <label>{{ __('Email') }} *</label>
                                                            <input class="form-control" type="email" name="email"
                                                                class="email" id="email"
                                                                placeholder="Your Email">
                                                        </div>
                                                    </div>
                                                    <div class=" col-md-6 col-12">
                                                        <div class="form-group booking-input mb-0">
                                                            <label>{{ __('Contact') }} *</label>
                                                            <input class="form-control" type="number" name="contact"
                                                                class="contact" id="contact"
                                                                placeholder="Phone Number">
                                                        </div>
                                                    </div>

                                                    <div class=" col-md-6 col-12">
                                                        <div class="form-group booking-input mb-0">
                                                            <label>{{ __('password') }} *</label>
                                                            <input class="form-control" type="password"
                                                                name="password" class="password" id="password"
                                                                placeholder="Your Password">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="tab-content " id="existing-user">
                                                <div class="row row-gap-3">
                                                    <div class=" col-md-6 col-12">
                                                        <div class="form-group booking-input mb-0">
                                                            <label>{{ __('Email') }} *</label>
                                                            <input class="form-control" type="email" name="email"
                                                                class="email" id="email"
                                                                placeholder="Your Email">
                                                        </div>
                                                    </div>
                                                    <div class=" col-md-6 col-12">
                                                        <div class="form-group booking-input mb-0">
                                                            <label>{{ __('password') }} *</label>
                                                            <input class="form-control" type="password"
                                                                name="password" class="password" id="password"
                                                                placeholder="Your Password">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="tab-content " id="guest-user">
                                                <div class="row row-gap-3">
                                                    <div class="col-md-6 col-12">
                                                        <div class="form-group booking-input mb-0">
                                                            <label>{{ __('Name') }} *</label>
                                                            <input class="form-control" type="text" name="name"
                                                                class="name" id="name"
                                                                placeholder="Your Full Name">
                                                        </div>
                                                    </div>
                                                    <div class=" col-md-6 col-12">
                                                        <div class="form-group booking-input mb-0">
                                                            <label>{{ __('Email') }} *</label>
                                                            <input class="form-control" type="email" name="email"
                                                                class="email" id="email"
                                                                placeholder="Your Email">
                                                        </div>
                                                    </div>
                                                    <div class=" col-md-6 col-12">
                                                        <div class="form-group booking-input mb-0">
                                                            <label>{{ __('Contact') }} *</label>
                                                            <input class="form-control" type="number" name="contact"
                                                                class="contact" id="contact"
                                                                placeholder="Phone Number">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                                <ul class="list-inline pull-right btn-wrapper">
                                    <li><button type="button"
                                            class="default-btn btn-transparent prev-step">{{ __('Back') }}</button>
                                    </li>
                                    <li><button type="submit"
                                            class="default-btn submit-step ">{{ __('Finish') }}</button></li>
                                </ul>
                            </div>

                            <input type="hidden" name="business_id" value="{{ $business->id }}">
                            <input type="hidden" name="payment" value="manually">
                            <div class="clearfix"></div>
                        </div>
                        {{ Form::close() }}
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="{{ asset('js/jquery.js') }}"></script>
    <script src="{{ asset('assets/js/custom-bootstrap.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap-datepicker.js') }}"></script>

    <script type="text/javascript">
        // ------------step-wizard-------------
        $(document).ready(function() {

            var daysOfWeek = <?php echo json_encode($combinedArray); ?>;
            var unavailableDates = <?php echo json_encode($businesholiday); ?>;
            $('#datepicker').datepicker({
                startDate: '+0d',
                format: 'dd-mm-yyyy',
                autoclose: true,
                daysOfWeekDisabled: daysOfWeek,
                datesDisabled: unavailableDates
            });

            $('.nav-tabs > li a[title]').tooltip();

            //Wizard
            $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {

                var target = $(e.target);

                if (target.parent().hasClass('disabled')) {
                    return false;
                }
            });

            $(document).on('click', '.next-step', function(e) {
                if (!validateCurrentStep()) {
                    return false;
                }
                var active = $('.wizard .nav-tabs li.active');
                active.next().removeClass('disabled');
                nextTab(active);
            });

            $(document).on('click', '.prev-step', function(e) {
                var active = $('.wizard .nav-tabs li.active');
                prevTab(active);
            });

        });


        function validateCurrentStep() {
            var isValid = true;

            // Validate fields for the current step
            var currentStep = $('.wizard .nav-tabs li.active a[data-toggle="tab"]').attr('href');

            if (currentStep === "#step1") {
                isValid = validateStep1();
            } else if (currentStep === "#step2") {
                isValid = validateStep2();
            } else if (currentStep === "#step3") {
                isValid = validateStep3();
            }
            // Add more conditions for additional steps if needed

            return isValid;
        }

        function validateStep1() {
            var service = $('.service').is(':checked');
            var staff = $('.staff').is(':checked');
            var location = $('.location').is(':checked');

            if (!service || !staff || !location) {
                alert('Please select all required field.');
                return false;
            }

            return true;
        }

        function validateStep2() {
            var date = $('#datepicker').val();
            var timeslot = $('.timeslot').is(':checked');

            if (!date || !timeslot) {
                alert('Please select all required field.');
                return false;
            }
            return true;
        }

        function validateStep3() {
            var name = $('.name').val();
            var contact = $('.contact').val();
            var email = $('.email').val();


            if (!name || !contact || !email) {
                alert('Please select all required field.');
                return false;
            }
            return true;
        }


        function nextTab(elem) {
            $(elem).next().find('a[data-toggle="tab"]').trigger('click');
        }

        function prevTab(elem) {
            $(elem).prev().find('a[data-toggle="tab"]').trigger('click');
        }


        $('.nav-tabs').on('click', 'li', function() {
            $('.nav-tabs li.active').removeClass('active');
            $(this).addClass('active');
        });

        $('.service').change(function() {
            updateAppointment();
        });
        $('#datepicker').on('changeDate', function() {
            updateAppointment();
        });

        function updateAppointment() {
            var selectedService = $('input[name="service"]:checked').val();
            var selectedDate = $('#datepicker').val();
            // Make an AJAX call
            var csrfToken = $('meta[name="csrf-token"]').attr('content');

            $.ajax({
                url: '{{ route('appointment.duration') }}', // Replace with your actual AJAX endpoint
                method: 'POST',
                data: {
                    service: selectedService,
                    date: selectedDate
                    // Add other data if needed
                },
                context: this,
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                success: function(response) {
                    if (response.result == 'success') {
                        // Handle the response from the server
                        var timeSlots = response.timeSlots;
                        // Display time slots below the datepicker
                        var timeSlotsContainer = $('#timeSlotsContainer');
                        timeSlotsContainer.empty(); // Clear previous time slots

                        if (timeSlots.length > 0) {
                            var timeSlotsList = $('<ul class="appoint-date-list row">');

                            timeSlots.forEach(function(timeSlot, index) {

                                var timeSlotLabel = $('<label class="d-block mb-0">');
                                var timeSlotDiv = $('<div class="px-2">');
                                var input = $('<input type="radio" class="timeslot">')
                                    .attr('name', 'duration')
                                    .attr('value', timeSlot.start + '-' + timeSlot.end)
                                    .attr('id', 'radio' + index);

                                var svgIcon = $('<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 512 512" fill="none">\
                                                                    <path d="M256 499.2C121.899 499.2 12.8 390.101 12.8 256C12.8 121.899 121.899 12.8 256 12.8C390.101 12.8 499.2 121.899 499.2 256C499.2 390.101 390.101 499.2 256 499.2ZM256 35.962C134.671 35.962 35.962 134.671 35.962 256C35.962 377.329 134.671 476.038 256 476.038C377.329 476.038 476.038 377.329 476.038 256C476.038 134.671 377.329 35.962 256 35.962Z" fill="black"></path>\
                                                                    <path d="M369.236 267.581H256C249.604 267.581 244.419 262.396 244.419 256V90.0062C244.419 83.6101 249.604 78.4253 256 78.4253C262.396 78.4253 267.581 83.6101 267.581 90.0062V244.419H369.236C375.632 244.419 380.817 249.604 380.817 256C380.817 262.396 375.632 267.581 369.236 267.581Z" fill="black"></path>\
                                                                    </svg>');

                                var span = $('<span>').append(svgIcon);
                                span.append($('<p class="mb-0 d-inline-block">').text(' ' + timeSlot
                                    .start + '-' + timeSlot.end));

                                timeSlotLabel.append(input);
                                timeSlotLabel.append(span);

                                timeSlotDiv.append(timeSlotLabel);
                                timeSlotsList.append($('<li class="col-lg-3 col-md-6 col-sm-6 col-12">')
                                    .append(timeSlotDiv));
                            });

                            timeSlotsContainer.append(timeSlotsList);
                        } else {
                            timeSlotsContainer.append('<p>No available time slots.</p>');
                        }
                    }
                }
            });
        }

        $(document).on('submit', '#appointment-form', function(e) {
            e.preventDefault(); // Prevent the default form submission

            var formData = new FormData(this);
            var customerTab = $('.tab-content.active').find('input');
            customerTab.each(function() {
                var inputName = $(this).attr('name');
                var inputValue = $(this).val();
                formData.append(inputName, inputValue);
            });
            var customerTab = customerTab.serialize();

            // formData.append('customerTab', customerTab);
            // Get the active tab

            var csrfToken = $('meta[name="csrf-token"]').attr('content');
            // Make an AJAX call
            $.ajax({
                url: '{{ route('appointment.form.submit') }}', // Replace with your actual route
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                success: function(response) {
                    if (response.msg == 'success') {
                        window.location.href = response.url;
                    } else {
                        $('.booking-error').removeClass('d-none').addClass('d-block');
                        $('.error-msg').html(response.error);
                    }
                }
            });
        });

        $('#appointment-form').on('change', function(e) {
            var paymentAction = $('[data-payment-action]:checked').data("payment-action");

            if (paymentAction) {
                $("#appointment-form").attr("action", paymentAction);
            } else {
                $("#appointment-form").attr("action", '');
            }
        });

        $(document).on('click', 'ul.tabs li', function() {
            $('.tabs-container .tab-content').find('input').val('');
            var $this = $(this);
            var $theTab = $this.attr('data-tab');

            if ($this.hasClass('active')) {
                // do nothing
            } else {
                $this.closest('.tabs-wrapper').find('ul.tabs li, .tabs-container .tab-content').removeClass(
                    'active');
                $('.tabs-container .tab-content[id="' + $theTab + '"], ul.tabs li[data-tab="' + $theTab + '"]')
                    .addClass('active');
            }
        });


        $('.tab-link').on('click', function() {
            var selectedTab = $(this).data('tab');
            $('#selected_tab').val(selectedTab);
        });
    </script>
    {{-- custom-js --}}
    <script type="text/javascript">
        {!! str_replace('</script>', '<\/script>', htmlspecialchars_decode($customJs)) !!}
    </script>
</body>

</html>
