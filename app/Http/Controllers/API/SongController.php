<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\DeleteSongsRequest;
use App\Http\Requests\API\SongListRequest;
use App\Http\Requests\API\SongUpdateRequest;
use App\Http\Requests\API\LyricsRequest;
use App\Http\Resources\AlbumResource;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\SongResource;
use App\Models\Song;
use App\Models\User;
use App\Repositories\AlbumRepository;
use App\Repositories\ArtistRepository;
use App\Repositories\SongRepository;
use App\Services\LibraryManager;
use App\Services\SongService;
use App\Values\SongUpdateData;
use Illuminate\Contracts\Auth\Authenticatable;

class SongController extends Controller
{
    /** @param User $user */
    public function __construct(
        private readonly SongService $songService,
        private readonly SongRepository $songRepository,
        private readonly AlbumRepository $albumRepository,
        private readonly ArtistRepository $artistRepository,
        private readonly LibraryManager $libraryManager,
        private readonly ?Authenticatable $user
    ) {
    }

    public function index(SongListRequest $request)
    {
        return SongResource::collection(
            $this->songRepository->getForListing(
                sortColumns: $request->sort ? explode(',', $request->sort) : ['songs.title'],
                sortDirection: $request->order ?: 'asc',
                ownSongsOnly: $request->boolean('own_songs_only'),
                scopedUser: $this->user
            )
        );
    }

    public function show(Song $song)
    {
        $this->authorize('access', $song);

        return SongResource::make($this->songRepository->getOne($song->id, $this->user));
    }

    // echo "HELLO";
    // echo "HELLO";
    // echo "HELLO";
    // echo $urlStr;

        // if ($response === false) {
    //     echo "cURL Error: " . curl_error($curl);
    //     curl_close($curl);
    //     return null;
    // }

        // if (json_last_error() !== JSON_ERROR_NONE) {
    //     echo "JSON Decode Error: " . json_last_error_msg();
    //     curl_close($curl);
    //     return null;
    // }

    // return $response;

      // public function apifetch()
    // {
    //     $url = "https://api.lyrics.ovh/v1/Ozzy%20Osbourne/Killer%20of%20Giants";
    //     $curl = curl_init();

    //     curl_setopt($curl, CURLOPT_URL, $url);
    //     curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    //     $retVal = json_decode(curl_exec($curl));
    //     curl_close($curl);
    //     echo $retVal;
    //     return $retVal;
    // }

    public function apifetch(LyricsRequest $request)
    {
        // $urlPart = $request->query('urlStr');
        $url = "https://api.lyrics.ovh/v1/"+$artist+'/'+$title;
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($curl);

        $retVal = json_decode($response, true); 

        curl_close($curl);
        // return $urlPart;
        return $retVal['lyrics'];
        
    }

    public function update(SongUpdateRequest $request)
    {
        // Don't use SongRepository::findMany() because it'd be already catered to the current user.
        Song::query()->findMany($request->songs)->each(fn (Song $song) => $this->authorize('edit', $song));

        $updatedSongs = $this->songService->updateSongs($request->songs, SongUpdateData::fromRequest($request));
        $albums = $this->albumRepository->getMany($updatedSongs->pluck('album_id')->toArray());

        $artists = $this->artistRepository->getMany(
            array_merge(
                $updatedSongs->pluck('artist_id')->all(),
                $updatedSongs->pluck('album_artist_id')->all()
            )
        );

        return response()->json([
            'songs' => SongResource::collection($updatedSongs),
            'albums' => AlbumResource::collection($albums),
            'artists' => ArtistResource::collection($artists),
            'removed' => $this->libraryManager->prune(),
        ]);
    }

    public function destroy(DeleteSongsRequest $request)
    {
        // Don't use SongRepository::findMany() because it'd be already catered to the current user.
        Song::query()->findMany($request->songs)->each(fn (Song $song) => $this->authorize('delete', $song));

        $this->songService->deleteSongs($request->songs);

        return response()->noContent();
    }
}
