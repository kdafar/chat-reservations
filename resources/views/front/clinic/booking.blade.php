<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HealthFirst Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .hero { background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%); color: white; padding: 100px 0; }
        .feature-card { transition: transform 0.3s; border-radius: 16px; border: none; }
        .feature-card:hover { transform: translateY(-8px); box-shadow: 0 12px 40px rgba(13,148,136,0.15); }
        .feature-icon { width: 64px; height: 64px; background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 28px; color: white; }
        .step-indicator { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; }
        .step-indicator.active { background: #0d9488; color: white; }
        .step-indicator.completed { background: #10b981; color: white; }
        .step-indicator.inactive { background: #e5e7eb; color: #9ca3af; }
        .time-slot-btn.active { background: #0d9488; border-color: #0d9488; color: white; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#"><i class="bi bi-heart-pulse-fill me-2" style="color:#0d9488;"></i>HealthFirst</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="#testimonials">Testimonials</a></li>
                    <li class="nav-item ms-3">
                        <button class="btn" style="background:#0d9488;color:white;border-radius:12px;" data-bs-toggle="modal" data-bs-target="#bookingModal">
                            <i class="bi bi-calendar-check me-2"></i>Book Now
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-3 fw-bold mb-4">Your Health, Our Priority</h1>
                    <p class="lead mb-4">Experience world-class healthcare. Book your appointment today.</p>
                    <button class="btn btn-light btn-lg me-3" style="border-radius:12px;" data-bs-toggle="modal" data-bs-target="#bookingModal">
                        <i class="bi bi-calendar-plus me-2"></i>Book Now
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-light">
        <div class="container">
            <div class="row g-4 text-center">
                <div class="col-md-3"><div class="card p-4 border-0 shadow-sm"><h2 class="fw-bold" style="color:#0d9488;">50K+</h2><p class="text-muted mb-0">Happy Patients</p></div></div>
                <div class="col-md-3"><div class="card p-4 border-0 shadow-sm"><h2 class="fw-bold" style="color:#0d9488;">120+</h2><p class="text-muted mb-0">Expert Doctors</p></div></div>
                <div class="col-md-3"><div class="card p-4 border-0 shadow-sm"><h2 class="fw-bold" style="color:#0d9488;">15+</h2><p class="text-muted mb-0">Years Experience</p></div></div>
                <div class="col-md-3"><div class="card p-4 border-0 shadow-sm"><h2 class="fw-bold" style="color:#0d9488;">4.9</h2><p class="text-muted mb-0">Patient Rating</p></div></div>
            </div>
        </div>
    </section>

    <section id="services" class="py-5 my-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold mb-3">Our Medical Services</h2>
                <p class="lead text-muted">Comprehensive healthcare solutions</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card feature-card h-100 p-4">
                        <div class="feature-icon mb-3"><i class="bi bi-heart-pulse"></i></div>
                        <h4 class="fw-bold mb-3">Cardiology</h4>
                        <p class="text-muted">Advanced heart care with expert cardiologists.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100 p-4">
                        <div class="feature-icon mb-3"><i class="bi bi-prescription2"></i></div>
                        <h4 class="fw-bold mb-3">General Medicine</h4>
                        <p class="text-muted">Comprehensive primary care for all health concerns.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100 p-4">
                        <div class="feature-icon mb-3"><i class="bi bi-bandaid"></i></div>
                        <h4 class="fw-bold mb-3">Emergency Care</h4>
                        <p class="text-muted">24/7 emergency services with rapid response.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100 p-4">
                        <div class="feature-icon mb-3"><i class="bi bi-person-hearts"></i></div>
                        <h4 class="fw-bold mb-3">Pediatrics</h4>
                        <p class="text-muted">Specialized care for children with experienced pediatricians.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100 p-4">
                        <div class="feature-icon mb-3"><i class="bi bi-virus"></i></div>
                        <h4 class="fw-bold mb-3">Laboratory</h4>
                        <p class="text-muted">Advanced diagnostic testing with accurate results.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100 p-4">
                        <div class="feature-icon mb-3"><i class="bi bi-capsule"></i></div>
                        <h4 class="fw-bold mb-3">Pharmacy</h4>
                        <p class="text-muted">On-site pharmacy with expert consultation.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="testimonials" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold mb-3">Patient Testimonials</h2>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 p-4 border-0 shadow-sm">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle d-flex align-items-center justify-center me-3" style="width:50px;height:50px;background:#0d9488;color:white;">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <div><h6 class="fw-bold mb-0">Sarah Johnson</h6><div class="text-warning">★★★★★</div></div>
                        </div>
                        <p class="text-muted">"Excellent service and caring staff. Highly recommend!"</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 p-4 border-0 shadow-sm">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle d-flex align-items-center justify-center me-3" style="width:50px;height:50px;background:#0d9488;color:white;">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <div><h6 class="fw-bold mb-0">Michael Chen</h6><div class="text-warning">★★★★★</div></div>
                        </div>
                        <p class="text-muted">"Professional doctors and modern facilities. Thank you!"</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 p-4 border-0 shadow-sm">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle d-flex align-items-center justify-center me-3" style="width:50px;height:50px;background:#0d9488;color:white;">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <div><h6 class="fw-bold mb-0">Emma Williams</h6><div class="text-warning">★★★★★</div></div>
                        </div>
                        <p class="text-muted">"Fast, efficient, and compassionate care for my family."</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-heart-pulse-fill me-2"></i>HealthFirst</h5>
                    <p class="text-white-50">Your trusted healthcare partner.</p>
                </div>
                <div class="col-md-4">
                    <h6 class="fw-bold mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#services" class="text-white-50 text-decoration-none">Services</a></li>
                        <li class="mb-2"><a href="#testimonials" class="text-white-50 text-decoration-none">Testimonials</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6 class="fw-bold mb-3">Contact</h6>
                    <p class="text-white-50 mb-2"><i class="bi bi-telephone me-2"></i>+965 1234 5678</p>
                    <p class="text-white-50 mb-2"><i class="bi bi-envelope me-2"></i>info@healthfirst.com</p>
                </div>
            </div>
            <hr class="my-4" style="opacity:0.1;">
            <div class="text-center text-white-50"><p class="mb-0">&copy; 2024 HealthFirst. All rights reserved.</p></div>
        </div>
    </footer>

    <div class="modal fade" id="bookingModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius:20px;">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">Book Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="d-flex justify-content-between mb-4 position-relative">
                        <div style="position:absolute;top:20px;left:0;right:0;height:2px;background:#e5e7eb;z-index:0;"></div>
                        <div class="text-center position-relative" style="z-index:1;">
                            <div class="step-indicator active" id="s1">1</div>
                            <small class="d-block mt-2">Location</small>
                        </div>
                        <div class="text-center position-relative" style="z-index:1;">
                            <div class="step-indicator inactive" id="s2">2</div>
                            <small class="d-block mt-2">Time</small>
                        </div>
                        <div class="text-center position-relative" style="z-index:1;">
                            <div class="step-indicator inactive" id="s3">3</div>
                            <small class="d-block mt-2">Details</small>
                        </div>
                        <div class="text-center position-relative" style="z-index:1;">
                            <div class="step-indicator inactive" id="s4">✓</div>
                            <small class="d-block mt-2">Done</small>
                        </div>
                    </div>

                    <div id="step1">
                        <h5 class="fw-bold mb-3">Select Location</h5>
                        <select class="form-select form-select-lg mb-3" id="branch" style="border-radius:12px;">
                            <option>Downtown Medical Center</option>
                            <option>Westside Health Clinic</option>
                            <option>Eastgate Medical Plaza</option>
                        </select>
                        <label class="form-label fw-semibold">Patients</label>
                        <div class="btn-group w-100">
                            <input type="radio" class="btn-check" name="size" id="p1" checked>
                            <label class="btn btn-outline-primary" for="p1">1</label>
                            <input type="radio" class="btn-check" name="size" id="p2">
                            <label class="btn btn-outline-primary" for="p2">2</label>
                            <input type="radio" class="btn-check" name="size" id="p3">
                            <label class="btn btn-outline-primary" for="p3">3</label>
                            <input type="radio" class="btn-check" name="size" id="p4">
                            <label class="btn btn-outline-primary" for="p4">4+</label>
                        </div>
                    </div>

                    <div id="step2" class="d-none">
                        <h5 class="fw-bold mb-3">Choose Date & Time</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control form-control-lg" id="date" style="border-radius:12px;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Time</label>
                                <div class="row g-2" id="times">
                                    <div class="col-6"><button class="btn btn-outline-secondary time-slot-btn w-100" data-time="09:00">09:00</button></div>
                                    <div class="col-6"><button class="btn btn-outline-secondary time-slot-btn w-100" data-time="10:00">10:00</button></div>
                                    <div class="col-6"><button class="btn btn-outline-secondary time-slot-btn w-100" data-time="14:00">14:00</button></div>
                                    <div class="col-6"><button class="btn btn-outline-secondary time-slot-btn w-100" data-time="15:00">15:00</button></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="step3" class="d-none">
                        <h5 class="fw-bold mb-3">Your Details</h5>
                        <input type="text" class="form-control form-control-lg mb-3" id="name" placeholder="Full Name" style="border-radius:12px;">
                        <input type="tel" class="form-control form-control-lg mb-3" id="phone" placeholder="Phone Number" style="border-radius:12px;">
                        <textarea class="form-control" id="notes" rows="3" placeholder="Medical Notes (Optional)" style="border-radius:12px;"></textarea>
                    </div>

                    <div id="step4" class="d-none text-center py-4">
                        <div class="mb-4">
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width:80px;height:80px;background:#0d9488;">
                                <i class="bi bi-check-lg text-white" style="font-size:48px;"></i>
                            </div>
                        </div>
                        <h4 class="fw-bold mb-3">Booking Confirmed!</h4>
                        <div class="card border-0 bg-light mx-auto p-4" style="max-width:400px;border-radius:16px;">
                            <p class="mb-2"><small class="text-muted">Reference:</small> <strong id="code">HF-001234</strong></p>
                            <p class="mb-2"><small class="text-muted">Date:</small> <strong id="confDate">-</strong></p>
                            <p class="mb-0"><small class="text-muted">Name:</small> <strong id="confName">-</strong></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button class="btn btn-secondary" id="prev" style="border-radius:12px;display:none;">Previous</button>
                    <button class="btn" id="next" style="background:#0d9488;color:white;border-radius:12px;">Next</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let step = 1;
        const data = {time:''};
        
        function updateStep() {
            for(let i=1; i<=4; i++) {
                document.getElementById('step'+i).classList.add('d-none');
                const ind = document.getElementById('s'+i);
                ind.className = 'step-indicator ' + (i<step ? 'completed' : i==step ? 'active' : 'inactive');
                if(i<step) ind.textContent = '✓';
                else if(i<4) ind.textContent = i;
            }
            document.getElementById('step'+step).classList.remove('d-none');
            document.getElementById('prev').style.display = step>1 ? 'block' : 'none';
            document.getElementById('next').textContent = step==4 ? 'Close' : step==3 ? 'Confirm' : 'Next';
        }
        
        document.getElementById('next').onclick = () => {
            if(step==4) { bootstrap.Modal.getInstance(document.getElementById('bookingModal')).hide(); return; }
            if(step==3) {
                data.name = document.getElementById('name').value;
                data.phone = document.getElementById('phone').value;
                data.date = document.getElementById('date').value;
                document.getElementById('code').textContent = 'HF-'+Math.floor(Math.random()*999999);
                document.getElementById('confDate').textContent = data.date + ' at ' + data.time;
                document.getElementById('confName').textContent = data.name;
            }
            step++; updateStep();
        };
        
        document.getElementById('prev').onclick = () => { step--; updateStep(); };
        
        document.querySelectorAll('.time-slot-btn').forEach(btn => {
            btn.onclick = () => {
                document.querySelectorAll('.time-slot-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                data.time = btn.dataset.time;
            };
        });
        
        updateStep();
    </script>
</body>
</html>