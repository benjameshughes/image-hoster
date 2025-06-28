<div>
{{--    <div class="my-4">--}}
{{--        <flux:button wire:click="deleteAll">Delete All</flux:button>--}}
{{--    </div>--}}
{{--    <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">--}}
{{--        <table class="min-w-full divide-y divide-gray-200">--}}
{{--            <thead class="bg-gray-50">--}}
{{--            <tr>--}}
{{--                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">--}}
{{--                    Name--}}
{{--                </th>--}}
{{--                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">--}}
{{--                    Url--}}
{{--                </th>--}}
{{--                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">--}}
{{--                    Actions--}}
{{--                </th>--}}
{{--            </tr>--}}
{{--            </thead>--}}
{{--            <tbody class="bg-white divide-y divide-gray-200">--}}
{{--            @forelse($images as $image)--}}
{{--                <tr>--}}
{{--                    <td class="px-6 py-4 whitespace-nowrap">--}}
{{--                        <div class="flex items-center">--}}
{{--                            <div class="flex-shrink-0 h-10 w-10">--}}
{{--                                <img class="h-10 w-10 rounded-full"--}}
{{--                                     src="{{ Storage::disk('spaces')->url($image->path) }}"--}}
{{--                                     alt="">--}}
{{--                            </div>--}}
{{--                            <div class="ml-4">--}}
{{--                                <div class="text-sm font-medium text-gray-900">--}}
{{--                                    {{ $image->name }}--}}
{{--                                </div>--}}
{{--                                <span>{{ $image->created_at }}</span>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </td>--}}
{{--                    <td class="px-6 py-4 whitespace-nowrap">--}}
{{--                        <div class="text-sm text-gray-900">--}}
{{--                            <x-copy-to-clipboard>--}}
{{--                                {{ Storage::disk('spaces')->url($image->path) }}--}}
{{--                            </x-copy-to-clipboard>--}}

{{--                        </div>--}}
{{--                    </td>--}}
{{--                    <td class="px-6 py-4 whitespace-nowrap">--}}
{{--                        <div class="text-sm text-gray-900">--}}
{{--                            <flux:button type="button" variant="primary"--}}
{{--                                         wire:click="view({{$image->id}})"--}}
{{--                                         target="_blank">View--}}
{{--                            </flux:button>--}}
{{--                            <flux:button type="button" variant="primary" wire:click="download({{$image->id}})">Download--}}
{{--                            </flux:button>--}}
{{--                            <flux:button type="button" variant="danger" wire:click="delete({{$image->id}})">Delete--}}
{{--                            </flux:button>--}}
{{--                        </div>--}}
{{--                    </td>--}}
{{--                </tr>--}}
{{--            @empty--}}
{{--                <tr>--}}
{{--                    <td class="px-6 py-4 whitespace-nowrap">--}}
{{--                        <div class="flex items-center col-span-full">--}}
{{--                            Nah bro--}}
{{--                        </div>--}}
{{--                    </td>--}}
{{--                </tr>--}}
{{--            @endforelse--}}
{{--            </tbody>--}}
{{--        </table>--}}
{{--        <x-action-message on="ImageDeleted">--}}
{{--            {{__('Image Delete!')}}--}}
{{--        </x-action-message>--}}
{{--        <x-action-message on="textCopied">--}}
{{--            {{__('URL Copied To Clipboard')}}--}}
{{--        </x-action-message>--}}
{{--    </div>--}}
{{--    {{$images->links()}}--}}
    <x-mary-table :headers="$headers" :rows="$rows" striped @row-click="alert($event.detail.name)"/>

</div>