<html>

<head>
  <title>Image Viewer</title>

  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/dat-gui/0.7.3/dat.gui.min.js"></script>
  <script type="text/javascript" src="https://unpkg.com/three@latest/build/three.min.js"></script>
  <script type="text/javascript" src="https://unpkg.com/ami.js@next/build/ami.min.js"></script>
	
  <link rel="stylesheet" href="styles.css">
</head>

<body>
  <div id="my-gui-container"></div>
  <div id="container"></div>

<script>
/* ========================= UTIL.JS: START ============================= */
    const file = '<?php echo  $currenturl ?>';
    // const file = 'http://localhost/ami_test/uploads/adi_brain.nii.gz';
    // const file = 'https://cdn.rawgit.com/FNNDSC/data/master/nifti/adi_brain/adi_brain.nii.gz';

    const colors = {
    red: 0x00FFFF,
    darkGrey: 0x000000,   // 0x353535 - default
    };
/* ========================= UTIL.JS: END ============================= */
 /* ===================== INDEX JS NII/ DICOM LOADER CODE: START ====================  */
    // import { colors, file } from './utils.js';

    // Setup renderer
    const container = document.getElementById('container');
    const renderer = new THREE.WebGLRenderer({
    antialias: true,
    });
    renderer.setSize(container.offsetWidth, container.offsetHeight);
    renderer.setClearColor(colors.darkGrey, 1);
    renderer.setPixelRatio(window.devicePixelRatio);
    container.appendChild(renderer.domElement);

    const scene = new THREE.Scene();

    const camera = new AMI.OrthographicCamera(
    container.clientWidth / -2,
    container.clientWidth / 2,
    container.clientHeight / 2,
    container.clientHeight / -2,
    0.1,
    10000
    );

    // Setup controls
    const controls = new AMI.TrackballOrthoControl(camera, container);
    controls.staticMoving = true;
    controls.noRotate = true;
    camera.controls = controls;

    const onWindowResize = () => {
    camera.canvas = {
        width: container.offsetWidth,
        height: container.offsetHeight,
    };
    camera.fitBox(2);

    renderer.setSize(container.offsetWidth, container.offsetHeight);
    };
    window.addEventListener('resize', onWindowResize, false);

    const loader = new AMI.VolumeLoader(container);
    loader
    .load(file)
    .then(() => {
        const series = loader.data[0].mergeSeries(loader.data);
        const stack = series[0].stack[0];
        loader.free();

        const stackHelper = new AMI.StackHelper(stack);
        stackHelper.bbox.visible = false;
        stackHelper.border.color = colors.red;
        scene.add(stackHelper);

        gui(stackHelper);

        // center camera and interactor to center of bouding box
        // for nicer experience
        // set camera
        const worldbb = stack.worldBoundingBox();
        const lpsDims = new THREE.Vector3(
        worldbb[1] - worldbb[0],
        worldbb[3] - worldbb[2],
        worldbb[5] - worldbb[4]
        );

        const box = {
        center: stack.worldCenter().clone(),
        halfDimensions: new THREE.Vector3(lpsDims.x + 10, lpsDims.y + 10, lpsDims.z + 10),
        };

        // init and zoom
        const canvas = {
        width: container.clientWidth,
        height: container.clientHeight,
        };

        camera.directions = [stack.xCosine, stack.yCosine, stack.zCosine];
        camera.box = box;
        camera.canvas = canvas;
        camera.update();
        camera.fitBox(2);
    })
    .catch(error => {
        window.console.log('oops... something went wrong...');
        window.console.log(error);
    });

    const animate = () => {
    controls.update();
    renderer.render(scene, camera);

    requestAnimationFrame(function() {
        animate();
    });
    };

    animate();

    const gui = stackHelper => {
    const gui = new dat.GUI({
        autoPlace: false,
    });

    const customContainer = document.getElementById('my-gui-container');
    customContainer.appendChild(gui.domElement);
    const camUtils = {
        invertRows: false,
        invertColumns: false,
        rotate45: false,
        rotate: 0,
        orientation: 'default',
        convention: 'radio',
    };

    // camera
    const cameraFolder = gui.addFolder('Camera');
    const invertRows = cameraFolder.add(camUtils, 'invertRows');
    invertRows.onChange(() => {
        camera.invertRows();
    });

    const invertColumns = cameraFolder.add(camUtils, 'invertColumns');
    invertColumns.onChange(() => {
        camera.invertColumns();
    });

    const rotate45 = cameraFolder.add(camUtils, 'rotate45');
    rotate45.onChange(() => {
        camera.rotate();
    });

    cameraFolder
        .add(camera, 'angle', 0, 360)
        .step(1)
        .listen();

    const orientationUpdate = cameraFolder.add(camUtils, 'orientation', [
        'default',
        'axial',
        'coronal',
        'sagittal',
    ]);
    orientationUpdate.onChange(value => {
        camera.orientation = value;
        camera.update();
        camera.fitBox(2);
        stackHelper.orientation = camera.stackOrientation;
    });

    const conventionUpdate = cameraFolder.add(camUtils, 'convention', ['radio', 'neuro']);
    conventionUpdate.onChange(value => {
        camera.convention = value;
        camera.update();
        camera.fitBox(2);
    });

    cameraFolder.open();

    const stackFolder = gui.addFolder('Stack');
    stackFolder
        .add(stackHelper, 'index', 0, stackHelper.stack.dimensionsIJK.z - 1)
        .step(1)
        .listen();
    stackFolder
        .add(stackHelper.slice, 'interpolation', 0, 1)
        .step(1)
        .listen();
    stackFolder.open();
    };
 /* ===================== INDEX JS NII/ DICOM LOADER CODE: END ====================  */
</script>

  <!-- google analytics -->
  <script>
	(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
	(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
	m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
	})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
	
	ga('create', 'UA-39303022-3', 'auto');
	var page = '/ami/lessons/03';
	ga('send', 'pageview', page);
  </script>
</body>

</html>