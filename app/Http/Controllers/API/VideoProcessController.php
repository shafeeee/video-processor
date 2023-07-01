<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use FFMpeg\FFProbe;
use Illuminate\Support\Facades\Storage;
use Aws\ElasticTranscoder\ElasticTranscoderClient;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;


/**
 * get the video as post, check the video size, if less than 60 seconds-
 * upload it into s3 first, then using elastic transcoder , convert the -
 * video size to 480p,720p. 
 * TODO : it should 144p, 240p, 360p, 480p, 720p and 1080p, compression level also TODO
 */
class VideoProcessController extends Controller
{
    public function index(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'video' => 'required|mimetypes:video/mp4,video/quicktime|max:60000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => $validator->errors()->first('video'),
            ], 422);
        }

        // Access the uploaded video file
        $uploadedVideo = $request->file('video');

        // Get the video duration in seconds
        $videoDuration = $this->getVideoDuration($uploadedVideo->path());

        if ($videoDuration > 60) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'The video must be less than 60 seconds in length.',
            ], 422);
        }

        $response = Storage::disk('s3')->put('videos', $uploadedVideo);
        $s3Url = Storage::disk('s3')->path($response);
        $data = $this->transcodeVideo($s3Url);
        


        return response()->json([
            'urls' => json_encode($data),
            'success' => true,
            'message' => 'Video uploaded successfully.',
        ], 200);
    }

    /**
     * A simple function to check the video length
     * [composer require php-ffmpeg/php-ffmpeg]
     * 
     * @param path $videoPath
     * @return duration
     */
    private function getVideoDuration($videoPath)
    {

        $ffprobe = FFProbe::create();
        $duration = $ffprobe->format($videoPath)->get('duration');

        return $duration;
    }


    /**
     * transcode the video using aws elastic transcoder - We can -
     * write this as a laravel jobs to handle the api very well.
     *
     * TODO: make this function as laravel jobs
     * TODO: pipelineid, version, region, credentials should be from ENV
     * 
     * @param url $url
     * @return array urls
     */
    private function transcodeVideo($url)
    {

        // Set the necessary parameters
        $pipelineId = '1688219413406-henn1w';
        $inputUrl = $url;
        $outputKeyPrefix = "processVideo/";

        // Create a new instance of the ElasticTranscoder client, these parameters should be from ENV
        $transcoder = new ElasticTranscoderClient([
            'version' => 'latest',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => 'AKIAXJDEWMGN5BPOTZ2H',
                'secret' => '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',
            ],
        ]);

        // Define the preset IDs for different resolutions- right now i am using only 2
        // $presetIds = ['preset-id-for-144p', 'preset-id-for-240p', 'preset-id-for-360p', 'preset-id-for-480p', 'preset-id-for-720p', 'preset-id-for-1080p'];

        $presetIds = ['1688220032283-582q0v' => '720', '1688220093423-5wtui9' => '480'];
        $outputUrls = [];

        // Process the video for each preset
        foreach ($presetIds as $presetId => $resolution) {
            try {
                $outputKey = $outputKeyPrefix . 'output_' . time() . $presetId . '.mp4';
                $job = $transcoder->createJob([
                    'PipelineId' => $pipelineId,
                    'Input' => [
                        'Key' => $inputUrl,
                    ],
                    'Outputs' => [
                        [
                            'Key' => $outputKey,
                            'PresetId' => $presetId,
                        ],
                    ],
                ]);
                // read job id to take the s3 output URL
                $jobId = $job['Job']['Id'];
                $jobDetails = $transcoder->readJob(['Id' => $jobId]);
                $outputUrl = $jobDetails['Job']['Output']['Key'];

                // Store the output URL in the array
                $outputUrls[$resolution]['urls'] = $outputUrl;
                $outputUrls[$resolution]['job_status'] = $jobDetails['Job']['Status'];
                $outputUrls[$resolution]['job_id'] = $jobDetails['Job']['Id'];
            } catch (ElasticTranscoderException $e) {
                // Handle exception/error if needed
                $jobStatuses[$jobId] = 'Error: ' . $e->getMessage();
                dd($e->getMessage());
            }
        }

        return $outputUrls;
    }

    /**
     * another api point to chekc the job is done frequently
     *
     * @param string $jobId
     * @return void
     */
    public function getJobStatus(Request $request) {
        $jobId = $request->post('job_id');
        try {
            // Create an instance of the ElasticTranscoder client-  these parameters should be from ENV
            $transcoder = new ElasticTranscoderClient([
                'version' => 'latest',
                'region' => 'us-east-1',
                'credentials' => [
                    'key' => 'AKIAXJDEWMGN5BPOTZ2H',
                    'secret' => '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',
                ],
            ]);

            // Read job details using the job ID
            $jobDetails = $transcoder->readJob(['Id' => $jobId]);

            // Get output URLs and file sizes
            $outputUrls = [];
            foreach ($jobDetails['Job']['Outputs'] as $output) {
                $outputKey = $output['Key'];
                $outputFileSize = $this->getFileSize($outputKey);

                $outputUrls[] = [
                    'url' => $this->getS3ObjectUrl($outputKey),
                    'file_size' => $outputFileSize,
                ];
            }

            return response()->json([
                'status' => $jobDetails['Job']['Status'],
                'output_urls' => $outputUrls,
            ], 200);
        } catch (ElasticTranscoderException $e) {
            // Handle exception/error if needed
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * get file size
     * need to optimize
     * @param [type] $key
     * @return void
     */
    private function getFileSize($key) {
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => 'AKIAXJDEWMGN5BPOTZ2H',
                'secret' => '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',
            ],
        ]);

        $headObject = $s3Client->headObject([
            'Bucket' => 'aetrovideobucket',
            'Key' => $key,
        ]);

        return $headObject['ContentLength'];
    }

    /**
     * return the URL
     *
     * @param string $key
     * @return url
     */
    private function getS3ObjectUrl($key) {
        return Storage::disk('s3')->url($key);
    }
}
