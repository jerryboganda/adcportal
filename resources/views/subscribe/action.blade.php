@permission('subscriber delete')
    <div class="action-btn">
        <form method="POST" action="{{ route('subscribes.destroy', $Subscribe->id) }}" id="user-form-{{ $Subscribe->id }}">
            @csrf
            @method('DELETE')
            <input name="_method" type="hidden" value="DELETE">
            <button type="button" class="btn btn-sm  bg-danger align-items-center show_confirm"
                data-bs-toggle="tooltip" title='Delete'>
                <span class="text-white"> <i class="ti ti-trash"></i></span>
            </button>
        </form>
    </div>
@endpermission
