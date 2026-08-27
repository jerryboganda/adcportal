@permission('contact delete')
    <div class="action-btn">
        <form method="POST" action="{{ route('contacts.destroy', $contact->id) }}" id="user-form-{{ $contact->id }}">
            @csrf
            @method('DELETE')
            <input name="_method" type="hidden" value="DELETE">
            <button type="button" class="btn btn-sm  bg-danger d-inline align-items-center show_confirm"
                data-bs-toggle="tooltip" title='Delete'>
                <span class="text-white"> <i class="ti ti-trash" aria-hidden="true"></i></span>
            </button>
        </form>
    </div>
@endpermission
