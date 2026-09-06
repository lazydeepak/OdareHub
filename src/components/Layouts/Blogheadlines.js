import React from 'react'
import '../Layouts/Blogheadlines.css'

function Tablets() {
    return (
        <div>
          <div className="row">
            <div className="forheader">
            <h2> Blog Headlines</h2>
            </div>
           

          <div className="card mb-6" style={{maxWidth: '540px'}}>
        <div className="row g-0">
          <div className="col-md-8">
            <img src="bh1.jpg" alt="One Plus 8T" />
          </div>
          <div className="col-md-8">
            <div className="card-body">
              <h5 className="card-title">One Plus 8T</h5>
              <p className="card-text">OnePlus 8T smartphone was launched on 14th October 2020. The phone comes with a 6.55-inch 
                 touchscreen display with a resolution of 1080x2400 pixels at a pixel density of 402 pixels per inch (ppi) and an aspect ratio
                 of 20:9. It comes with 12GB of RAM. The OnePlus 8T runs Android 11 and is powered by a 4500mAh non-removable battery.
                 The OnePlus 8T supports proprietary fast charging.As far as the cameras are concerned, the OnePlus 8T on the rear packs a 48-megapixel
                  primary camera with an f/1.7 aperture; a second 16-megapixel camera with an f/2.2 aperture; a third 5-megapixel camera and a fourth 2-megapixel camera.</p>
            </div>
          </div>
        </div>
      </div>

      <div className="card mb-6" style={{maxWidth: '540px'}}>
        <div className="row g-0">
          <div className="col-md-8">
            <img src="bh2.jpg" alt="Samsung Galaxy S21 Ultra" />
          </div>
          <div className="col-md-8">
            <div className="card-body">
              <h5 className="card-title">Samsung Galaxy S21 Ultra</h5>
              <p className="card-text">Samsung Galaxy S21 Ultra smartphone was launched on 14th January 2021. 
              The phone comes with a 6.80-inch touchscreen display with a resolution of 1440x3220 pixels at a pixel density 
              of 515 pixels per inch (ppi). Samsung Galaxy S21 Ultra is powered by a 2.2GHz octa-core Samsung Exynos 2100 processor 
              that features 3 cores clocked at 2.8GHz, 4 cores clocked at 2.2GHz and 1 cores clocked at 2.9GHz. It comes with 12GB of RAM.
              The Samsung Galaxy S21 Ultra runs Android 11 and is powered by a 5000mAh battery. The Samsung Galaxy S21 Ultra supports wireless
              charging, as well as proprietary fast charging.</p>
              
            </div>
          </div>
        </div>
      </div>

          </div>

        </div>
    )
}

export default Tablets
