<html>

<head>
  <title>Image Viewer</title>

  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/dat-gui/0.7.3/dat.gui.min.js"></script>
  <script type="text/javascript" src="https://unpkg.com/three@latest/build/three.min.js"></script>
  <script type="text/javascript" src="https://unpkg.com/ami.js@next/build/ami.min.js"></script>
	
  <link rel="stylesheet" href="styles.css">
  <style>
    
.card-body {
    display: none !important;
}

/* .pb-3 {
    display: none !important;
}*/
#my-gui-container {
      position: fixed;
      top: 57px;
      right: 10px;
      z-index: 1;
  } 



   /* .diicom canvas {
        height: 380px !important;
    }

    #my-gui-container {
      position: fixed;
      top: 57px;
      right: 10px;
      z-index: 1;
  }

  body#page-mod-medicalimageviewer-view canvas {
	    height: 495px !important;
	}

	.card {
	    display: none;
	} */
  </style>
</head>

<body>
<!-- <div style="height:100px">A</div> -->
  <div id="my-gui-container"></div>
  <div id="container" class="diicom"></div>
<!-- <div style="height:200px">B</div> -->

  <!-- google analytics -->
  <script>

      /* ========================= UTIL.JS: START ============================= */
    const filenames = [
        '36444280',
        '36444294',
        '36444308',
        '36444322',
        '36444336',
        '36444350',
        '36444364',
        '36444378',
        '36444392',
        '36444406',
        '36444434',
        '36444448',
        '36444462',
        '36444476',
        '36444490',
        '36444504',
        '36444518',
        '36444532',
        '36746856',
        ];

    const files = filenames.map(filename => {
    // return `https://cdn.rawgit.com/FNNDSC/data/master/dicom/adi_brain/${filename}`;
    return '<?php echo  $currenturl ?>';
    });

    const colors = {
    red: 0xff0000,
    blue: 0x0000ff,
    darkGrey: 0x353535,
    };


/* ========================= UTIL.JS: END ============================= */
 /* ===================== INDEX JS NII/ DICOM LOADER CODE: START ====================  */
    // import { colors, files } from './utils.js';

// Classic ThreeJS setup
const container = document.getElementById('container');
const renderer = new THREE.WebGLRenderer({
  antialias: true,
});
renderer.setSize(container.offsetWidth, container.offsetHeight);
renderer.setClearColor(colors.darkGrey, 1);
renderer.setPixelRatio(window.devicePixelRatio);
container.appendChild(renderer.domElement);

const scene = new THREE.Scene();

const camera = new THREE.PerspectiveCamera(
  45,
  container.offsetWidth / container.offsetHeight,
  0.1,
  1000
);
camera.position.x = 150;
camera.position.y = 150;
camera.position.z = 100;

const controls = new AMI.TrackballControl(camera, container);

const onWindowResize = () => {
  camera.aspect = container.offsetWidth / container.offsetHeight;
  camera.updateProjectionMatrix();

  renderer.setSize(container.offsetWidth, container.offsetHeight);
};

window.addEventListener('resize', onWindowResize, false);

// Load DICOM images and create AMI Helpers
const loader = new AMI.VolumeLoader(container);
loader
  .load(files)
  .then(() => {
    const series = loader.data[0].mergeSeries(loader.data);
    const stack = series[0].stack[0];
    loader.free();

    const stackHelper = new AMI.StackHelper(stack);
    stackHelper.bbox.color = colors.red;
    stackHelper.border.color = colors.blue;

    scene.add(stackHelper);

    // build the gui
    gui(stackHelper);

    // center camera and interactor to center of bouding box
    const centerLPS = stackHelper.stack.worldCenter();
    camera.lookAt(centerLPS.x, centerLPS.y, centerLPS.z);
    camera.updateProjectionMatrix();
    controls.target.set(centerLPS.x, centerLPS.y, centerLPS.z);
  })
  .catch(error => {
    window.console.log('oops... something went wrong...');
    window.console.log(error);
  });

const animate = () => {
  controls.update();
  renderer.render(scene, camera);

  requestAnimationFrame(() => {
    animate();
  });
};
animate();

// setup gui
const gui = stackHelper => {
  const stack = stackHelper.stack;
  const gui = new dat.GUI({
    autoPlace: false,
  });
  const customContainer = document.getElementById('my-gui-container');
  customContainer.appendChild(gui.domElement);

  // stack
  const stackFolder = gui.addFolder('Stack');
  // index range depends on stackHelper orientation.
  const index = stackFolder
    .add(stackHelper, 'index', 0, stack.dimensionsIJK.z - 1)
    .step(1)
    .listen();
  const orientation = stackFolder
    .add(stackHelper, 'orientation', 0, 2)
    .step(1)
    .listen();
  orientation.onChange(value => {
    index.__max = stackHelper.orientationMaxIndex;
    stackHelper.index = Math.floor(index.__max / 2);
  });
  stackFolder.open();

  // slice
  const sliceFolder = gui.addFolder('Slice');
  sliceFolder
    .add(stackHelper.slice, 'windowWidth', 1, stack.minMax[1] - stack.minMax[0])
    .step(1)
    .listen();
  sliceFolder
    .add(stackHelper.slice, 'windowCenter', stack.minMax[0], stack.minMax[1])
    .step(1)
    .listen();
  sliceFolder.add(stackHelper.slice, 'intensityAuto').listen();
  sliceFolder.add(stackHelper.slice, 'invert');
  sliceFolder.open();

  // bbox
  const bboxFolder = gui.addFolder('Bounding Box');
  bboxFolder.add(stackHelper.bbox, 'visible');
  bboxFolder.addColor(stackHelper.bbox, 'color');
  bboxFolder.open();

  // border
  const borderFolder = gui.addFolder('Border');
  borderFolder.add(stackHelper.border, 'visible');
  borderFolder.addColor(stackHelper.border, 'color');
  borderFolder.open();
};

 /* ===================== INDEX JS NII/ DICOM LOADER CODE: END ====================  */

	(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
	(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
	m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
	})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
	
	ga('create', 'UA-39303022-3', 'auto');
	var page = '/ami/lessons/01';
	ga('send', 'pageview', page);
  </script>
</body>

</html>