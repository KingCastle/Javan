<?php

namespace Javan\Http\Controllers;

use Cart;
use Illuminate\Http\Request;
use Javan\Billing\BillingInterface;
use Javan\Booking;
use Javan\Event;
use Javan\Jobs\SendBookingConfirmation;
use Javan\Jobs\SendBookingRefundEmail;
use Javan\Jobs\SendBookingToAdmin;

class BookingsController extends Controller
{
	protected $billing;

	/**
	 * ShoppingCartsController constructor.
	 *
	 * @param \Javan\Billing\BillingInterface $billing
	 */
	public function __construct(BillingInterface $billing)
	{
		$this->middleware('auth');
		$this->middleware('admin.manager', ['except' => ['create', 'store', 'show']]);
		$this->billing = $billing;
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index()
	{
		$sortBy    = request('sortBy');
		$direction = request('direction');
		$params    = compact('sortBy', 'direction');

		if ($sortBy && $direction) {
			$bookings = Booking::with('user', 'event')->orderBy($params['sortBy'], $params['direction'])->paginate(50);
		} else {
			$bookings = Booking::with('user', 'event')->latest()->paginate(50);
		}

		return view('bookings.index', compact('bookings'));
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create()
	{
		if ( ! Cart::instance('event')->count()) {
			return redirect()->route('music');
		}

		$event = Event::findOrFail(Cart::instance('event')->content()->first()->id);

		if ($event->seatsRemaining() < 0) {
			flash()->overlay(
				'There is no enough seats!',
				'We are either fully booked or there is not enough seats available. This is because someone booked faster in the same time!',
				'error'
			);
			Cart::instance('event')->destroy();

			return redirect()->route('music');
		}

		return view('bookings.create');
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request)
	{
		$event = Event::findOrFail(Cart::instance('event')->content()->first()->id);

		if (expired($event->finish)) {
			flash()->error('Sorry we could not book you!', 'This event is expired');
			Cart::instance('event')->destroy();

			return redirect()->route('music');
		}
		if ($event->seatsRemaining() <= 0) {
			flash()->overlay(
				'Sorry we could not book you!',
				'We are fully booked or there is not enough seats available. This is because someone booked faster in the same time!',
				'error'
			);

			return redirect()->route('music');
		}

		$charge  = $this->billing->charge([
			'total' => Cart::instance('event')->subtotal() * 100,
			'email' => auth()->user()->email,
			'token' => $request->input('stripe-token'),
		]);
		$booking = Booking::create([
			'user_id'   => auth()->user()->id,
			'event_id'  => Cart::instance('event')->content()->first()->id,
			'charge_id' => $charge->id,
			'seats'     => Cart::instance('event')->content()->first()->qty,
			'total'     => Cart::instance('event')->subtotal() * 100,
			'ticket'    => random_int(1000, 9999), // strtoupper(str_random(5))
			'active'    => TRUE,
		]);
		$this->dispatch(new SendBookingToAdmin($booking));
		$this->dispatch(new SendBookingConfirmation($booking));
		Cart::instance('event')->destroy();
		flash()->overlay('Payment was successful', "Your ticket has been confirmed. Please check your email for more info. <br> Your ticket number is <strong>{$booking->ticket}</strong>");

		return redirect()->route('member.bookings');
	}

	/**
	 * @param \Javan\Booking $bookings
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function update(Booking $bookings)
	{
		if ( ! $bookings->charge_id) {
			flash()->error('Error!', 'Could not find the charge.');

			return back();
		}
		$refund = $this->billing->refund(['charge' => $bookings->charge_id]);
		$bookings->update([
			'refund_id' => $refund->id,
			'seats'     => 0,
			'active'    => FALSE,
		]);

		$this->dispatch(new SendBookingRefundEmail($bookings));
		flash()->success('Success', 'Payment has been refunded successfully.');

		return back();
	}

	/**
	 * @param \Javan\Booking $bookings
	 * @return \Illuminate\Http\RedirectResponse
	 * @throws \Exception
	 */
	public function destroy(Booking $bookings)
	{
		$bookings->delete();
		flash()->success('Success', 'Booking has been deleted successfully.');

		return back();
	}
}
