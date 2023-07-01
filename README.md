Task Name: Video Processing task

description:
1. post video , the system will check the length of the video. if it is greater than 60, it return the validation error.
else, upload to s3, transcode into 480p, 720 p now. we can add the other formats later. then return the job status, possible urls
etc. then, i wrote a endpoint to see the jobstatus, actual filesize and object url

2. installation process (you don't need it)
    1. create a aws account
    2. set up s3
    3. create bucket for input and ouput (transcoder need video path, can't use the post video directly)
    4. create IAM user, made the permissions perfectly
    5. install ffmepg laravel package (composer require php-ffmpeg/php-ffmpeg) to get the length of the post video
    6. install laravel package aws file system (composer require league/flysystem-aws-s3-v3)
    7. install the laravel sanctum to secure the apis, didn't configure it. i just leave it for easy testing
    8. there are some TODOs, optimization needed on the code and a missing task as well, didn't do it becuase of the less time. but, mention on the code comments.
    9. we can do it the transcode as a job, but i didn't do it. but , i mentioned that on the code. 

3. How to test it?

    1. used postman, http://127.0.0.1:8000/api/videoProcess (as POST), on the body, click formdata, video as parameter , on the value section select file.
    2. it will return an output that contain, job id, job status , expected URL. example:
            {
                "urls": "{\"720\":{\"urls\":\"processVideo\\/output_16882384191688220032283-582q0v.mp4\",\"job_status\":\"Submitted\",\"job_id\":\"1688238436190-1k0oaq\"},\"480\":{\"urls\":\"processVideo\\/output_16882384361688220093423-5wtui9.mp4\",\"job_status\":\"Submitted\",\"job_id\":\"1688238436791-sdb3m1\"}}",
                "success": true,
                "message": "Video uploaded successfully."
            }
    3. take the job id , for example : 1688236768664-hr2lql, call http://127.0.0.1:8000/api/getJobStatus, job_id as parameter, value should take from the above videoProcess response. i already gave an example

    4. this api will give the jobstatus, appropriate url, and filesize. example:
            {
                "status": "Complete",
                "output_urls": [
                    {
                        "url": "http://aetrovideobucket.s3.amazonaws.com/processVideo/output_16882367521688220032283-582q0v.mp4",
                        "file_size": 4076140
                    }
                ]
            }




