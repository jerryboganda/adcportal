{{-- Appointment Reports Popup --}}
<div class="appointment-reports-container">
    @php
        $company_settings = getCompanyAllSetting();
        $appointmentNumber = \App\Models\Appointment::appointmentNumberWithFormat($appointment->id, $company_settings);
    @endphp

    {{-- Header Info --}}
    <div class="report-header mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="report-icon">
                <i class="ti ti-file-description text-primary" style="font-size: 2rem;"></i>
            </div>
            <div>
                <h6 class="mb-1">{{ $appointmentNumber }}</h6>
                <small class="text-muted">
                    {{ $appointment->CustomerData ? $appointment->CustomerData->name : ($appointment->name ?? 'Guest') }}
                    • {{ $appointment->date }} {{ $appointment->time }}
                </small>
            </div>
        </div>
    </div>

    {{-- Upload Section --}}
    <div class="upload-section mb-4">
        <form id="reportUploadForm" enctype="multipart/form-data">
            @csrf
            <div class="upload-dropzone" id="uploadDropzone">
                <input type="file" name="report_file" id="reportFileInput" class="d-none"
                    accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.xls,.xlsx">
                <div class="dropzone-content text-center py-4">
                    <i class="ti ti-cloud-upload text-primary mb-2" style="font-size: 2.5rem;"></i>
                    <p class="mb-1">{{ __('Drag & drop report file here') }}</p>
                    <p class="text-muted small mb-2">{{ __('or') }}</p>
                    <button type="button" class="btn btn-primary btn-sm"
                        onclick="document.getElementById('reportFileInput').click();">
                        <i class="ti ti-upload me-1"></i>{{ __('Browse Files') }}
                    </button>
                    <p class="text-muted small mt-2 mb-0">{{ __('PDF, Images, Word, Excel (Max 20MB)') }}</p>
                </div>
            </div>
            <div class="upload-progress d-none mt-3">
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                        style="width: 0%"></div>
                </div>
                <small class="text-muted mt-1 d-block text-center">{{ __('Uploading...') }}</small>
            </div>
        </form>
    </div>

    {{-- Reports List --}}
    <div class="reports-list-section">
        <h6 class="mb-3 d-flex align-items-center gap-2">
            <i class="ti ti-files" aria-hidden="true"></i>
            {{ __('Uploaded Reports') }}
            <span class="badge bg-primary rounded-pill" id="reportCount">{{ $appointment->reports->count() }}</span>
        </h6>

        <div id="reportsList">
            @forelse($appointment->reports as $report)
                <div class="report-item d-flex align-items-center justify-content-between p-3 mb-2 rounded border"
                    data-report-id="{{ $report->id }}">
                    <div class="d-flex align-items-center gap-3">
                        <div class="file-icon">
                            @php
                                $ext = strtolower($report->file_extension);
                                $iconClass = 'ti-file';
                                $iconColor = 'text-secondary';

                                if (in_array($ext, ['pdf'])) {
                                    $iconClass = 'ti-file-type-pdf';
                                    $iconColor = 'text-danger';
                                } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                    $iconClass = 'ti-photo';
                                    $iconColor = 'text-success';
                                } elseif (in_array($ext, ['doc', 'docx'])) {
                                    $iconClass = 'ti-file-type-doc';
                                    $iconColor = 'text-primary';
                                } elseif (in_array($ext, ['xls', 'xlsx'])) {
                                    $iconClass = 'ti-file-spreadsheet';
                                    $iconColor = 'text-success';
                                }
                            @endphp
                            <i class="ti {{ $iconClass }} {{ $iconColor }}" style="font-size: 1.75rem;"></i>
                        </div>
                        <div>
                            <p class="mb-0 fw-medium text-truncate" style="max-width: 250px;"
                                title="{{ $report->file_name }}">
                                {{ $report->file_name }}
                            </p>
                            <small class="text-muted">
                                {{ $report->formatted_file_size }} • {{ $report->created_at->format('d M Y, h:i A') }}
                            </small>
                        </div>
                    </div>
                    <div class="report-actions d-flex gap-2">
                        <a href="{{ route('appointment.reports.download', $report->id) }}"
                            class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="{{ __('Download') }}">
                            <i class="ti ti-download" aria-hidden="true"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-report-btn"
                            data-report-id="{{ $report->id }}" data-bs-toggle="tooltip" title="{{ __('Delete') }}">
                            <i class="ti ti-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            @empty
                <div class="no-reports text-center py-4" id="noReportsMessage">
                    <i class="ti ti-folder-off text-muted mb-2" style="font-size: 3rem;"></i>
                    <p class="text-muted mb-0">{{ __('No reports uploaded yet') }}</p>
                    <small class="text-muted">{{ __('Upload a report using the form above') }}</small>
                </div>
            @endforelse
        </div>
    </div>
</div>

<style>
    .appointment-reports-container {
        max-height: 70vh;
        overflow-y: auto;
    }

    .upload-dropzone {
        border: 2px dashed var(--bs-primary);
        border-radius: 10px;
        background: rgba(var(--bs-primary-rgb), 0.03);
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .upload-dropzone:hover,
    .upload-dropzone.dragover {
        background: rgba(var(--bs-primary-rgb), 0.08);
        border-color: var(--bs-primary);
    }

    .upload-dropzone.dragover {
        transform: scale(1.01);
    }

    .report-item {
        transition: all 0.2s ease;
        background: var(--bs-light);
    }

    .report-item:hover {
        background: var(--bs-gray-100);
        border-color: var(--bs-primary) !important;
    }

    .file-icon {
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bs-white);
        border-radius: 8px;
        border: 1px solid var(--bs-border-color);
    }

    /* Dark mode support */
    [data-bs-theme="dark"] .upload-dropzone {
        background: rgba(var(--bs-primary-rgb), 0.1);
    }

    [data-bs-theme="dark"] .report-item {
        background: var(--bs-dark-bg-subtle);
    }

    [data-bs-theme="dark"] .file-icon {
        background: var(--bs-gray-800);
    }
</style>

<script>
    $(document).ready(function () {
        const appointmentId = {{ $appointment->id }};
        const uploadUrl = '{{ route("appointment.reports.upload", $appointment->id) }}';
        const csrfToken = '{{ csrf_token() }}';

        // Drag and drop handling
        const dropzone = $('#uploadDropzone');
        const fileInput = $('#reportFileInput');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.on(eventName, function (e) {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.on(eventName, function () {
                $(this).addClass('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.on(eventName, function () {
                $(this).removeClass('dragover');
            });
        });

        dropzone.on('drop', function (e) {
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                uploadFile(files[0]);
            }
        });

        // File input change
        fileInput.on('change', function () {
            if (this.files.length > 0) {
                uploadFile(this.files[0]);
            }
        });

        // Upload file function
        function uploadFile(file) {
            const formData = new FormData();
            formData.append('report_file', file);
            formData.append('_token', csrfToken);

            $('.upload-progress').removeClass('d-none');
            $('.upload-progress .progress-bar').css('width', '0%');

            $.ajax({
                url: uploadUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function () {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function (e) {
                        if (e.lengthComputable) {
                            const percent = (e.loaded / e.total) * 100;
                            $('.upload-progress .progress-bar').css('width', percent + '%');
                        }
                    });
                    return xhr;
                },
                success: function (response) {
                    if (response.success) {
                        toastrs('Success', response.message, 'success');
                        addReportToList(response.report);
                        updateReportCount(1);
                        $('#noReportsMessage').remove();
                    } else {
                        toastrs('Error', response.error || 'Upload failed', 'error');
                    }
                },
                error: function (xhr) {
                    const error = xhr.responseJSON ? xhr.responseJSON.error : 'Upload failed';
                    toastrs('Error', error, 'error');
                },
                complete: function () {
                    $('.upload-progress').addClass('d-none');
                    fileInput.val('');
                }
            });
        }

        // Add report to list dynamically
        function addReportToList(report) {
            const ext = report.file_name.split('.').pop().toLowerCase();
            let iconClass = 'ti-file';
            let iconColor = 'text-secondary';

            if (['pdf'].includes(ext)) {
                iconClass = 'ti-file-type-pdf';
                iconColor = 'text-danger';
            } else if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
                iconClass = 'ti-photo';
                iconColor = 'text-success';
            } else if (['doc', 'docx'].includes(ext)) {
                iconClass = 'ti-file-type-doc';
                iconColor = 'text-primary';
            } else if (['xls', 'xlsx'].includes(ext)) {
                iconClass = 'ti-file-spreadsheet';
                iconColor = 'text-success';
            }

            const html = `
                <div class="report-item d-flex align-items-center justify-content-between p-3 mb-2 rounded border" 
                     data-report-id="${report.id}">
                    <div class="d-flex align-items-center gap-3">
                        <div class="file-icon">
                            <i class="ti ${iconClass} ${iconColor}" style="font-size: 1.75rem;"></i>
                        </div>
                        <div>
                            <p class="mb-0 fw-medium text-truncate" style="max-width: 250px;" title="${report.file_name}">
                                ${report.file_name}
                            </p>
                            <small class="text-muted">
                                ${report.file_size} • ${report.created_at}
                            </small>
                        </div>
                    </div>
                    <div class="report-actions d-flex gap-2">
                        <a href="/appointment/reports/${report.id}/download" 
                           class="btn btn-sm btn-outline-primary" 
                           data-bs-toggle="tooltip" 
                           title="{{ __('Download') }}">
                            <i class="ti ti-download" aria-hidden="true"></i>
                        </a>
                        <button type="button" 
                                class="btn btn-sm btn-outline-danger delete-report-btn" 
                                data-report-id="${report.id}"
                                data-bs-toggle="tooltip" 
                                title="{{ __('Delete') }}">
                            <i class="ti ti-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            `;

            $('#reportsList').prepend(html);

            // Reinitialize tooltips
            $('[data-bs-toggle="tooltip"]').tooltip();
        }

        // Delete report
        $(document).on('click', '.delete-report-btn', function () {
            const reportId = $(this).data('report-id');
            const $item = $(this).closest('.report-item');

            if (confirm('{{ __("Are you sure you want to delete this report?") }}')) {
                $.ajax({
                    url: `/appointment/reports/${reportId}`,
                    type: 'DELETE',
                    data: { _token: csrfToken },
                    success: function (response) {
                        if (response.success) {
                            toastrs('Success', response.message, 'success');
                            $item.fadeOut(300, function () {
                                $(this).remove();
                                updateReportCount(-1);

                                // Show no reports message if empty
                                if ($('#reportsList .report-item').length === 0) {
                                    $('#reportsList').html(`
                                        <div class="no-reports text-center py-4" id="noReportsMessage">
                                            <i class="ti ti-folder-off text-muted mb-2" style="font-size: 3rem;"></i>
                                            <p class="text-muted mb-0">{{ __('No reports uploaded yet') }}</p>
                                            <small class="text-muted">{{ __('Upload a report using the form above') }}</small>
                                        </div>
                                    `);
                                }
                            });
                        }
                    },
                    error: function (xhr) {
                        const error = xhr.responseJSON ? xhr.responseJSON.error : 'Delete failed';
                        toastrs('Error', error, 'error');
                    }
                });
            }
        });

        // Update report count badge
        function updateReportCount(change) {
            const $badge = $('#reportCount');
            const current = parseInt($badge.text()) || 0;
            $badge.text(current + change);
        }

        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
    });
</script>
