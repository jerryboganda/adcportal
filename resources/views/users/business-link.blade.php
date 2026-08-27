<div class="modal-body">
    <div class="booking-data-table">
        <table class="table mb-0 pc-dt-simple" id="products">
            <thead>
                <tr>
                    <th>{{ __('Business Name') }}</th>
                    <th width="100px">{{ __('Business Link') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($businessLinks as $key => $value)
                    <tr>
                        <td>{{ $value['name'] }}</td>
                        <td class="text-end">
                            <input type="text" value="{{ $value['link'] }}" id="myInput_{{ $value['name'] }}"
                                class="form-control d-inline-block theme-link"  readonly>
                            <button class="btn btn-outline-primary  gap-2 d-flex align-iteams-center" type="button"
                                onclick="myFunction('myInput_{{ $value['name'] }}')" id="button-addon2"><i
                                    class="ti ti-copy" aria-hidden="true"></i>
                                {{ __('Business Link') }}</button>
                        </td>

                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<script>
function myFunction(id) {
        var copyText = document.getElementById(id);
        copyText.select();
        copyText.setSelectionRange(0, 99999)
        document.execCommand("copy");
        toastrs('Success', '{{ __('Link copied') }}', 'success')
    }
</script>
